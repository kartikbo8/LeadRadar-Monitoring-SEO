<?php

namespace App\Jobs;

use App\Exceptions\WebsiteAuditException;
use App\Models\HealthCheckRun;
use App\Models\MonitoredSite;
use App\Services\HealthReportBuilder;
use App\Services\LinkHealthChecker;
use App\Services\PlaywrightResponsiveAuditService;
use App\Services\TlsCertificateChecker;
use App\Services\WebsiteAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class RunMonitoredSiteHealthCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Crawl (up to 45s) + link checks (~60s) + browser renders (up to 240s). Keep below the queue retry_after (480s).
    public int $timeout = 420;

    public function __construct(
        public int $monitoredSiteId,
        public int $healthCheckRunId,
    ) {}

    public function handle(
        WebsiteAuditService $websiteAuditService,
        PlaywrightResponsiveAuditService $responsiveAuditService,
        ?LinkHealthChecker $linkChecker = null,
        ?TlsCertificateChecker $tlsChecker = null,
        ?HealthReportBuilder $reportBuilder = null,
    ): void {
        $linkChecker ??= app(LinkHealthChecker::class);
        $tlsChecker ??= app(TlsCertificateChecker::class);
        $reportBuilder ??= app(HealthReportBuilder::class);

        $site = MonitoredSite::query()->find($this->monitoredSiteId);
        $run = HealthCheckRun::query()->find($this->healthCheckRunId);

        if ($site === null || $run === null || $run->monitored_site_id !== $site->id) {
            return;
        }

        if ($site->verified_at === null) {
            $run->update([
                'status' => 'failed',
                'error' => 'Verify this domain with its DNS TXT record before scanning.',
                'scanned_at' => now(),
            ]);

            return;
        }

        $run->update([
            'status' => 'running',
            'error' => null,
        ]);

        try {
            $audit = $websiteAuditService->audit($site->website_url, includeHealthChecks: true);
            $responsiveResults = $responsiveAuditService->scan(
                $site->host,
                array_column(array_slice($audit['pages'], 0, PlaywrightResponsiveAuditService::MAX_PAGES), 'url'),
            );
            $linkResults = $linkChecker->check($audit['health_checks']['links_to_check'] ?? []);
            $tlsResults = $tlsChecker->check($site->host);
            $built = $reportBuilder->build($site->website_url, $audit, $responsiveResults, $linkResults, $tlsResults);

            $run->update([
                'status' => 'completed',
                'results' => [
                    'pages_scanned' => $audit['pages_scanned'],
                    'pages_skipped' => $audit['pages_skipped'],
                    'passive' => array_diff_key($audit['health_checks'], ['links_to_check' => true]),
                    'browser' => $responsiveResults,
                    'tls' => $tlsResults,
                    'issues' => $built['issues'],
                    'report' => $built['report'],
                    'checked_at' => now()->toIso8601String(),
                ],
                'scanned_at' => now(),
            ]);

            $site->update(['last_scanned_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            $errorMessage = $exception instanceof WebsiteAuditException || $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'An unexpected error occurred during the check. Review the application logs.';

            $run->update([
                'status' => 'failed',
                'error' => $errorMessage,
                'scanned_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        HealthCheckRun::query()
            ->whereKey($this->healthCheckRunId)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error' => 'The check stopped unexpectedly. Review the application logs.',
                'scanned_at' => now(),
            ]);
    }
}
