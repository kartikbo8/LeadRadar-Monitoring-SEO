<?php

namespace App\Http\Controllers;

use App\Exceptions\WebsiteAuditException;
use App\Services\CompetitorKeywordComparison;
use App\Services\WebsiteAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitorController extends Controller
{
    /**
     * How long a successful website scan is reused before the site is crawled again.
     */
    private const AUDIT_CACHE_SECONDS = 3600;

    public function index(
        Request $request,
        WebsiteAuditService $websiteAuditService,
        CompetitorKeywordComparison $keywordComparison,
    ): View {
        $searchAttempted = $request->query->has('website');
        $competitor = trim((string) $request->query('website', ''));
        $ownWebsite = trim((string) $request->query('your_website', ''));
        $audit = null;
        $searchError = null;
        $comparison = null;
        $ownAudit = null;
        $ownSiteError = null;

        if ($searchAttempted) {
            $validated = $request->validate([
                'website' => ['required', 'string', 'max:2048'],
                'your_website' => ['nullable', 'string', 'max:2048'],
            ]);

            $competitor = trim($validated['website']);
            $ownWebsite = trim($validated['your_website'] ?? '');

            try {
                $audit = $this->cachedAudit($websiteAuditService, $competitor);
            } catch (WebsiteAuditException $exception) {
                $searchError = $exception->getMessage();
            }

            if ($audit !== null && $ownWebsite !== '') {
                try {
                    $ownAudit = $this->cachedAudit($websiteAuditService, $ownWebsite);
                    $comparison = $keywordComparison->compare($audit, $ownAudit);
                } catch (WebsiteAuditException $exception) {
                    $ownSiteError = $exception->getMessage();
                }
            }
        }

        return view('companies.competitors', compact(
            'competitor',
            'ownWebsite',
            'audit',
            'ownAudit',
            'comparison',
            'searchAttempted',
            'searchError',
            'ownSiteError',
        ));
    }

    public function export(Request $request, WebsiteAuditService $websiteAuditService): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'website' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $audit = $this->cachedAudit($websiteAuditService, $validated['website']);
        } catch (WebsiteAuditException) {
            return redirect()->route('competitors.index', ['website' => $validated['website']]);
        }

        $fileName = 'competitor-keywords-'.Str::slug(str_replace('.', '-', $audit['domain'])).'.csv';

        return response()->streamDownload(function () use ($audit): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Keyword', 'Occurrences', 'Pages', 'Page URLs'], escape: '');

            foreach ($audit['keywords'] as $keyword) {
                fputcsv($output, [
                    $keyword['term'],
                    $keyword['occurrences'],
                    $keyword['pages'],
                    implode(' ', array_column($keyword['page_details'], 'url')),
                ], escape: '');
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Scan a website, reusing a recent successful scan of the same normalized URL.
     *
     * @return array<string, mixed>
     *
     * @throws WebsiteAuditException
     */
    private function cachedAudit(WebsiteAuditService $websiteAuditService, string $website): array
    {
        return Cache::remember(
            'competitor-audit:v2:'.sha1($websiteAuditService->normalizeWebsite($website)),
            self::AUDIT_CACHE_SECONDS,
            fn (): array => $websiteAuditService->audit($website),
        );
    }
}
