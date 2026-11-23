<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

class PlaywrightResponsiveAuditService
{
    public const MAX_PAGES = WebsiteAuditService::MAX_HEALTH_CHECK_PAGES;

    /** Two short discovery passes plus up to 25 pages × 3 viewports rendered in parallel. */
    public const PROCESS_TIMEOUT_SECONDS = 240;

    public function __construct(private PublicHostResolver $hostResolver) {}

    /**
     * @param  list<string>  $urls
     * @return array{pages: list<array<string, mixed>>, blocked_external_requests: int, external_hosts: list<array{host: string, types: list<string>}>, offsite_navigations: list<array{from: string, to: string}>}
     */
    public function scan(string $host, array $urls): array
    {
        $host = strtolower(rtrim($host, '.'));

        if (
            filter_var($host, FILTER_VALIDATE_IP) !== false
            ||
            filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')
        ) {
            throw new WebsiteAuditException('The monitored website has an invalid domain.');
        }

        $safeUrls = array_values(array_filter(
            array_unique(array_slice($urls, 0, self::MAX_PAGES)),
            fn (mixed $url): bool => is_string($url) && $this->isSameHostHttpUrl($url, $host),
        ));

        if ($safeUrls === []) {
            throw new WebsiteAuditException('No safe same-domain pages were available for the browser check.');
        }

        $input = json_encode([
            'host' => $host,
            'address' => $this->hostResolver->resolve($host),
            'urls' => $safeUrls,
        ], JSON_THROW_ON_ERROR);

        $process = Process::path(base_path())
            ->timeout(self::PROCESS_TIMEOUT_SECONDS)
            ->input($input)
            ->run(['node', base_path('scripts/health-audit.mjs')]);

        if (! $process->successful()) {
            Log::warning('The Playwright website health check process failed.', [
                'host' => $host,
                'exit_code' => $process->exitCode(),
                'error' => $process->errorOutput(),
            ]);

            throw new RuntimeException(
                'The browser-based check could not run. Confirm Node.js and the Playwright Chromium browser are installed.',
            );
        }

        try {
            $result = json_decode($process->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The browser-based check returned invalid data.', previous: $exception);
        }

        if (! is_array($result) || ! is_array($result['pages'] ?? null)) {
            throw new RuntimeException('The browser-based check returned an unexpected result.');
        }

        return [
            'pages' => $result['pages'],
            'blocked_external_requests' => (int) ($result['blocked_external_requests'] ?? 0),
            'external_hosts' => is_array($result['external_hosts'] ?? null) ? $result['external_hosts'] : [],
            'offsite_navigations' => is_array($result['offsite_navigations'] ?? null) ? $result['offsite_navigations'] : [],
        ];
    }

    private function isSameHostHttpUrl(string $url, string $host): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && strtolower(rtrim($parts['host'] ?? '', '.')) === $host
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port']);
    }
}
