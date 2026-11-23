<?php

namespace App\Http\Controllers;

use App\Exceptions\WebsiteAuditException;
use App\Jobs\RunMonitoredSiteHealthCheck;
use App\Models\MonitoredSite;
use App\Services\DomainOwnershipVerifier;
use App\Services\MetaTagOwnershipVerifier;
use App\Services\PublicHostResolver;
use App\Services\WebsiteAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class HealthMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $sites = $request->user()
            ->monitoredSites()
            ->with(['latestRun', 'runs' => fn ($query) => $query->latest()->limit(5)])
            ->latest()
            ->get();

        return view('health-monitor.index', compact('sites'));
    }

    public function store(
        Request $request,
        WebsiteAuditService $websiteAuditService,
        PublicHostResolver $hostResolver,
    ): RedirectResponse {
        $validated = $request->validate([
            'website' => ['required', 'string', 'max:2048'],
            'authorization' => ['required', 'accepted'],
        ]);

        [$websiteUrl, $host] = $this->normalizeSiteInput(
            $validated['website'], 'website', $websiteAuditService, $hostResolver,
        );

        if ($request->user()->monitoredSites()->where('host', $host)->exists()) {
            throw ValidationException::withMessages([
                'website' => 'This domain is already in your monitored sites.',
            ]);
        }

        $request->user()->monitoredSites()->create([
            'website_url' => $websiteUrl,
            'host' => $host,
            'verification_token' => Str::random(48),
        ]);

        return redirect()->route('health-monitor.index')
            ->with('status', 'Add the DNS TXT record shown below, then verify the domain.');
    }

    public function update(
        Request $request,
        int $siteId,
        WebsiteAuditService $websiteAuditService,
        PublicHostResolver $hostResolver,
    ): RedirectResponse {
        $site = $this->siteForUser($request, $siteId);
        $validated = $request->validate([
            'edit_website' => ['required', 'string', 'max:2048'],
            'authorization' => ['required', 'accepted'],
        ]);

        [$websiteUrl, $host] = $this->normalizeSiteInput(
            $validated['edit_website'], 'edit_website', $websiteAuditService, $hostResolver,
        );

        if ($websiteUrl === $site->website_url) {
            return back()->with('status', 'No changes to save.');
        }

        if ($site->runs()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->withErrors([
                'edit_website' => 'Wait for the current health check to finish before editing this website.',
            ]);
        }

        if ($host === $site->host) {
            $site->update(['website_url' => $websiteUrl]);

            return back()->with('status', 'Website URL updated. Domain verification is unchanged.');
        }

        if ($request->user()->monitoredSites()->where('host', $host)->whereKeyNot($site->id)->exists()) {
            throw ValidationException::withMessages([
                'edit_website' => 'This domain is already in your monitored sites.',
            ]);
        }

        DB::transaction(function () use ($site, $websiteUrl, $host): void {
            $site->runs()->delete();
            $site->update([
                'website_url' => $websiteUrl,
                'host' => $host,
                'verification_token' => Str::random(48),
                'verified_at' => null,
                'is_active' => false,
                'last_scanned_at' => null,
            ]);
        });

        return back()->with('status', 'Domain changed. Add the new DNS TXT record shown below, then verify the domain.');
    }

    public function verify(
        Request $request,
        int $siteId,
        DomainOwnershipVerifier $ownershipVerifier,
        MetaTagOwnershipVerifier $metaTagVerifier,
    ): RedirectResponse {
        $site = $this->siteForUser($request, $siteId);

        if ($site->verified_at !== null) {
            return back()->with('status', 'This domain is already verified.');
        }

        $validated = $request->validate([
            'method' => ['nullable', Rule::in(['dns', 'meta'])],
            'record_domain' => ['nullable', 'string', Rule::in($site->verificationDomains())],
        ]);

        if (($validated['method'] ?? 'dns') === 'meta') {
            try {
                $found = $metaTagVerifier->verify($site->website_url, $site->verification_token);
            } catch (WebsiteAuditException $exception) {
                return back()->withErrors([
                    'verification' => 'Could not read the homepage to check the meta tag: '.$exception->getMessage(),
                ]);
            }

            if (! $found) {
                return back()->withErrors([
                    'verification' => 'The leadradar-verification meta tag was not found on '.$site->website_url.' or does not match. Publish the site after adding it, then try again.',
                ]);
            }

            return $this->markVerified($site);
        }

        $recordDomain = $validated['record_domain'] ?? $site->host;

        if (! $ownershipVerifier->verify($recordDomain, $site->verification_token)) {
            return back()->withInput(['record_domain' => $recordDomain, 'record_site_id' => $site->id])->withErrors([
                'verification' => 'The DNS TXT record was not found at _leadradar-verification.'.$recordDomain.' or does not match. DNS changes may take time to propagate.',
            ]);
        }

        return $this->markVerified($site);
    }

    private function markVerified(MonitoredSite $site): RedirectResponse
    {
        $site->update([
            'verified_at' => now(),
            'is_active' => true,
        ]);

        return back()->with('status', 'Domain verified. Daily monitoring is now enabled.');
    }

    public function scan(Request $request, int $siteId): RedirectResponse
    {
        $site = $this->siteForUser($request, $siteId);

        if ($site->verified_at === null) {
            return back()->withErrors([
                'monitoring' => 'Verify this domain with its DNS TXT record before scanning.',
            ]);
        }

        if ($site->runs()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('status', 'A health check for this domain is already in progress.');
        }

        $run = $site->runs()->create(['status' => 'queued']);

        RunMonitoredSiteHealthCheck::dispatch($site->id, $run->id)->afterCommit();

        return back()->with('status', 'Website health check queued.');
    }

    /**
     * Download a completed health report as a self-contained HTML file that opens offline and prints to PDF.
     */
    public function downloadReport(Request $request, int $siteId, int $runId): Response
    {
        $site = $this->siteForUser($request, $siteId);
        $run = $site->runs()->findOrFail($runId);

        abort_unless($run->status === 'completed' && ($run->results['report']['version'] ?? 0) >= 2, 404);

        try {
            $styles = Vite::content('resources/css/app.css');
        } catch (Throwable) {
            $styles = '';
        }

        $html = view('health-monitor.download', [
            'site' => $site,
            'run' => $run,
            'styles' => $styles,
        ])->render();

        $reportDate = ($run->scanned_at ?? $run->created_at)->format('Y-m-d');
        $fileName = 'leadradar-health-report-'.Str::slug(str_replace('.', '-', $site->host)).'-'.$reportDate.'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName),
        ]);
    }

    public function toggle(Request $request, int $siteId): RedirectResponse
    {
        $site = $this->siteForUser($request, $siteId);
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($site->verified_at === null) {
            return back()->withErrors([
                'monitoring' => 'Verify the domain before enabling scheduled monitoring.',
            ]);
        }

        $site->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('status', $site->is_active
            ? 'Daily monitoring enabled.'
            : 'Daily monitoring paused.');
    }

    public function destroy(Request $request, int $siteId): RedirectResponse
    {
        $this->siteForUser($request, $siteId)->delete();

        return back()->with('status', 'Monitored website and its run history removed.');
    }

    private function siteForUser(Request $request, int $siteId): MonitoredSite
    {
        return $request->user()->monitoredSites()->findOrFail($siteId);
    }

    /**
     * @return array{0: string, 1: string} The stored root URL and its host.
     */
    private function normalizeSiteInput(
        string $website,
        string $field,
        WebsiteAuditService $websiteAuditService,
        PublicHostResolver $hostResolver,
    ): array {
        try {
            $parts = parse_url($websiteAuditService->normalizeWebsite($website));
            $host = strtolower(rtrim($parts['host'], '.'));
            $hostResolver->resolve($host);
        } catch (WebsiteAuditException $exception) {
            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        return [$parts['scheme'].'://'.$host.'/', $host];
    }
}
