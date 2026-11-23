<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteAuditService
{
    private const MAX_PAGES = 10;

    /** Health checks crawl deeper so every page can be checked for responsiveness. */
    public const MAX_HEALTH_CHECK_PAGES = 25;

    private const MAX_PAGE_BYTES = 1_000_000;

    private const CRAWL_TIME_LIMIT_SECONDS = 25;

    private const HEALTH_CHECK_CRAWL_TIME_LIMIT_SECONDS = 45;

    private const DEFACEMENT_MARKERS = [
        'hacked by',
        'site hacked',
        'website hacked',
        'defaced by',
    ];

    /** Words injected by common SEO-spam / pharma / gambling hacks. */
    private const SPAM_PATTERN = '/\b(?:viagra|cialis|levitra|kamagra|casino online|online casino|slot gacor|slot online|situs slot|togel|judi online|poker online|bandar bola|payday loans?|replica (?:watches|rolex)|cheap jerseys|porn|xxx videos?|escort service|buy followers|crypto doubler)\b/u';

    private const HIDDEN_STYLE_PATTERN = '/display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0(?:px)?\b|(?:left|top)\s*:\s*-\d{3,}px|opacity\s*:\s*0(?:\.0+)?\s*(?:;|$)/i';

    /** Packed or encoded JavaScript typical of injected malware. */
    private const OBFUSCATED_SCRIPT_PATTERN = '/eval\s*\(\s*(?:atob|unescape|decodeURIComponent|String\.fromCharCode|function\s*\(p,\s*a,\s*c,\s*k,\s*e)|document\.write\s*\(\s*(?:unescape|atob)|(?:\\\\x[0-9a-f]{2}){40,}|String\.fromCharCode\s*\((?:\s*\d+\s*,){40,}/i';

    private const STOP_WORDS = [
        'about', 'after', 'again', 'also', 'and', 'are', 'because', 'been', 'before', 'being', 'between',
        'but', 'can', 'could', 'did', 'does', 'for', 'from', 'have', 'here', 'into', 'its', 'more', 'most',
        'not', 'only', 'other', 'our', 'out', 'over', 'same', 'should', 'some', 'such', 'than', 'that',
        'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'under',
        'very', 'was', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'will', 'with', 'would',
        'you', 'your',
    ];

    public function __construct(private PublicHostResolver $hostResolver) {}

    /**
     * @return array{
     *     website: string,
     *     domain: string,
     *     pages_scanned: int,
     *     pages_skipped: int,
     *     total_words: int,
     *     missing_descriptions: int,
     *     keywords: list<array{term: string, occurrences: int, pages: int, page_details: list<array{index: int, url: string, title: string, occurrences: int}>}>,
     *     term_occurrences: array<string, int>,
     *     pages: list<array{url: string, title: string, description: string, headings: list<string>, word_count: int, keywords: list<array{term: string, occurrences: int}>}>,
     *     health_checks: array{
     *         enabled: bool,
     *         issues: list<array{code: string, severity: string, page: string, message: string}>,
     *         mobile_pages_checked: int,
     *         mobile_viewport_pages: int
     *     }
     * }
     */
    public function audit(string $website, bool $includeHealthChecks = false): array
    {
        $startUrl = $this->normalizeWebsite($website);
        $parts = parse_url($startUrl);
        $host = strtolower(rtrim($parts['host'], '.'));
        $scheme = $parts['scheme'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $resolvedAddress = $this->hostResolver->resolve($host);
        $queuedUrls = [$startUrl => true];
        $queue = [$startUrl];
        $pages = [];
        $pageKeywordCountsByPage = [];
        $pagesSkipped = 0;
        $keywordCounts = [];
        $totalWords = 0;
        $healthIssues = [];
        $mobilePagesChecked = 0;
        $mobileViewportPages = 0;
        $crawledUrls = [];
        $linkSources = [];
        $scriptHosts = [];
        $iframeHosts = [];
        $maxPages = $includeHealthChecks ? self::MAX_HEALTH_CHECK_PAGES : self::MAX_PAGES;
        $deadline = microtime(true) + ($includeHealthChecks ? self::HEALTH_CHECK_CRAWL_TIME_LIMIT_SECONDS : self::CRAWL_TIME_LIMIT_SECONDS);

        while ($queue !== [] && count($pages) < $maxPages && microtime(true) < $deadline) {
            $url = array_shift($queue);

            try {
                $response = $this->fetchHtml($url, $host, $port, $resolvedAddress);
            } catch (WebsiteAuditException $exception) {
                if ($url === $startUrl) {
                    throw $exception;
                }

                Log::info('Skipping an unavailable page during the website audit.', [
                    'host' => $host,
                    'path' => parse_url($url, PHP_URL_PATH),
                    'reason' => $exception->getMessage(),
                ]);
                $pagesSkipped++;

                if ($includeHealthChecks) {
                    $healthIssues[] = [
                        'code' => 'internal_page_unavailable',
                        'severity' => 'warning',
                        'page' => $url,
                        'message' => 'An internal page could not be loaded during this scan.',
                    ];
                }

                continue;
            }

            [$page, $links, $pageKeywordCounts, $pageSignals] = $this->analyzePage($url, $response['html']);
            $pages[] = $page;
            $crawledUrls[$url] = true;

            if ($includeHealthChecks) {
                foreach ($links as $link) {
                    $linkSources[$link] ??= ['url' => $link, 'found_on' => $url, 'internal' => true];
                }

                foreach ($pageSignals['external_links'] as $link) {
                    $linkSources[$link] ??= ['url' => $link, 'found_on' => $url, 'internal' => false];
                }

                $scriptHosts += array_fill_keys($pageSignals['script_hosts'], true);
                $iframeHosts += array_fill_keys($pageSignals['iframe_hosts'], true);
            }
            $totalWords += $page['word_count'];
            $pageKeywordCountsByPage[] = $pageKeywordCounts;

            if ($includeHealthChecks) {
                $mobilePagesChecked++;
                $mobileViewportPages += (int) $pageSignals['mobile_viewport_configured'];
                $healthIssues = array_merge(
                    $healthIssues,
                    $this->healthIssuesForPage($url, $response['headers'], $pageSignals),
                );
            }

            foreach ($pageKeywordCounts as $term => $count) {
                if (! isset($keywordCounts[$term])) {
                    $keywordCounts[$term] = [
                        'term' => $term,
                        'occurrences' => 0,
                        'pages' => 0,
                    ];
                }

                $keywordCounts[$term]['occurrences'] += $count;
                $keywordCounts[$term]['pages']++;
            }

            foreach ($links as $link) {
                if (! isset($queuedUrls[$link])) {
                    $queuedUrls[$link] = true;
                    $queue[] = $link;
                }
            }
        }

        uasort($keywordCounts, static function (array $first, array $second): int {
            return $second['occurrences'] <=> $first['occurrences']
                ?: strcmp($first['term'], $second['term']);
        });

        $topKeywords = array_slice(array_values($keywordCounts), 0, 20);

        foreach ($topKeywords as &$keyword) {
            $keyword['page_details'] = [];

            foreach ($pages as $pageIndex => $page) {
                $occurrences = $pageKeywordCountsByPage[$pageIndex][$keyword['term']] ?? null;

                if (! is_int($occurrences)) {
                    continue;
                }

                $keyword['page_details'][] = [
                    'index' => $pageIndex,
                    'url' => $page['url'],
                    'title' => $page['title'],
                    'occurrences' => $occurrences,
                ];
            }
        }
        unset($keyword);

        return [
            'website' => $startUrl,
            'domain' => $host,
            'pages_scanned' => count($pages),
            'pages_skipped' => $pagesSkipped,
            'total_words' => $totalWords,
            'missing_descriptions' => count(array_filter(
                $pages,
                static fn (array $page): bool => $page['description'] === '',
            )),
            'keywords' => $topKeywords,
            'term_occurrences' => array_column($keywordCounts, 'occurrences', 'term'),
            'pages' => $pages,
            'health_checks' => [
                'enabled' => $includeHealthChecks,
                'issues' => $healthIssues,
                'mobile_pages_checked' => $mobilePagesChecked,
                'mobile_viewport_pages' => $mobileViewportPages,
                // Links still to verify: everything found except pages the crawl already loaded successfully.
                'links_to_check' => array_values(array_filter(
                    $linkSources,
                    static fn (array $link): bool => ! isset($crawledUrls[$link['url']]),
                )),
                'script_hosts' => array_keys($scriptHosts),
                'iframe_hosts' => array_keys($iframeHosts),
            ],
        ];
    }

    public function normalizeWebsite(string $website): string
    {
        $website = trim($website);

        if (! str_contains($website, '://')) {
            $website = 'https://'.$website;
        }

        $parts = $this->parseUrl($website);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new WebsiteAuditException('Enter a valid public website URL, such as https://example.com.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(rtrim($parts['host'], '.'));
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = $parts['port'] ?? $defaultPort;

        if (
            filter_var($host, FILTER_VALIDATE_IP) !== false
            ||
            filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')
            || $port !== $defaultPort
        ) {
            throw new WebsiteAuditException('Enter a public website domain using standard HTTP or HTTPS.');
        }

        $path = $this->normalizePath($parts['path'] ?? '/');

        return $scheme.'://'.$host.$path;
    }

    /**
     * @return array{
     *     html: string,
     *     headers: array{
     *         strict_transport_security: string,
     *         content_security_policy: string,
     *         content_type_options: string,
     *         frame_options: string
     *     }
     * }
     */
    /**
     * Fetch a single public page's HTML using the same SSRF protections as the audit
     * (public-IP check, pinned DNS resolution, no redirects, size cap).
     *
     * @throws WebsiteAuditException
     */
    public function fetchPublicPageHtml(string $website): string
    {
        $url = $this->normalizeWebsite($website);
        $parts = $this->parseUrl($url);
        $host = strtolower(rtrim($parts['host'], '.'));
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

        return $this->fetchHtml($url, $host, $port, $this->hostResolver->resolve($host))['html'];
    }

    private function fetchHtml(string $url, string $host, int $port, string $resolvedAddress): array
    {
        if (! class_exists(DOMDocument::class)) {
            throw new WebsiteAuditException('The PHP DOM extension is required to analyze website content.');
        }

        if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            throw new WebsiteAuditException('Secure website scanning requires the PHP cURL extension.');
        }

        $curlAddress = str_contains($resolvedAddress, ':')
            ? '['.$resolvedAddress.']'
            : $resolvedAddress;
        $pageSizeExceeded = false;

        try {
            $response = Http::accept('text/html')
                ->connectTimeout(3)
                ->timeout(8)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:{$port}:{$curlAddress}"],
                    ],
                    'progress' => static function (
                        int $downloadTotal,
                        int $downloadedBytes,
                        int $uploadTotal,
                        int $uploadedBytes,
                    ) use (&$pageSizeExceeded): bool {
                        if ($downloadedBytes > self::MAX_PAGE_BYTES) {
                            $pageSizeExceeded = true;

                            return true;
                        }

                        return false;
                    },
                ])
                ->get($url);
        } catch (ConnectionException|RequestException $exception) {
            if ($pageSizeExceeded) {
                throw new WebsiteAuditException('A page is too large to scan safely.', previous: $exception);
            }

            throw new WebsiteAuditException('Could not connect to this website. Check the URL and try again.', previous: $exception);
        }

        if ($response->redirect()) {
            throw new WebsiteAuditException('This website redirects. Enter its final HTTPS address and try again.');
        }

        if (! $response->successful()) {
            throw new WebsiteAuditException('This website could not be scanned. Check that the address is publicly accessible.');
        }

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'text/html')) {
            throw new WebsiteAuditException('The address did not return an HTML webpage to analyze.');
        }

        $contentLength = $response->header('Content-Length');

        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > self::MAX_PAGE_BYTES) {
            throw new WebsiteAuditException('A page is too large to scan safely.');
        }

        $html = $response->body();

        if (strlen($html) > self::MAX_PAGE_BYTES) {
            throw new WebsiteAuditException('A page is too large to scan safely.');
        }

        return [
            'html' => $html,
            'headers' => [
                'strict_transport_security' => (string) $response->header('Strict-Transport-Security', ''),
                'content_security_policy' => (string) $response->header('Content-Security-Policy', ''),
                'content_type_options' => (string) $response->header('X-Content-Type-Options', ''),
                'frame_options' => (string) $response->header('X-Frame-Options', ''),
            ],
        ];
    }

    /**
     * @return array{
     *     0: array{url: string, title: string, description: string, headings: list<string>, word_count: int, keywords: list<array{term: string, occurrences: int}>},
     *     1: list<string>,
     *     2: array<string, int>,
     *     3: array{
     *         mobile_viewport_configured: bool,
     *         missing_image_alt_count: int,
     *         broken_fragment_count: int,
     *         possible_defacement_text: bool
     *     }
     * }
     */
    private function analyzePage(string $url, string $html): array
    {
        $document = new DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        if (! $loaded) {
            throw new WebsiteAuditException('A webpage could not be parsed as HTML.');
        }

        $xpath = new DOMXPath($document);
        $title = trim($xpath->query('//title')->item(0)?->textContent ?? '');
        $description = '';
        $mobileViewportConfigured = false;
        $metaDescriptions = $xpath->query('//meta[@name]');

        foreach ($metaDescriptions as $meta) {
            $metaName = strtolower($meta->getAttribute('name'));

            if ($metaName === 'description' && $description === '') {
                $description = trim($meta->getAttribute('content'));
            }

            if (
                $metaName === 'viewport'
                && str_contains(mb_strtolower($meta->getAttribute('content')), 'width=device-width')
            ) {
                $mobileViewportConfigured = true;
            }
        }

        $headings = [];

        foreach ($xpath->query('//h1 | //h2 | //h3') as $heading) {
            $text = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');

            if ($text !== '' && ! in_array($text, $headings, true)) {
                $headings[] = $text;
            }

            if (count($headings) === 10) {
                break;
            }
        }

        $securitySignals = $this->securitySignals($xpath, $url);

        foreach ($xpath->query('//script | //style | //noscript | //svg') as $ignoredNode) {
            $ignoredNode->parentNode?->removeChild($ignoredNode);
        }

        $bodyText = $xpath->query('//body')->item(0)?->textContent ?? $document->textContent;
        $text = html_entity_decode($bodyText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $words);

        $wordCount = count($words[0]);
        $pageKeywordCounts = $this->countKeywords($text);
        $links = $this->discoverLinks($xpath, $url);
        $missingImageAltCount = 0;

        foreach ($xpath->query('//img[@src]') as $image) {
            if (! $image->hasAttribute('alt')) {
                $missingImageAltCount++;
            }
        }

        $elementIds = [];

        foreach ($xpath->query('//*[@id]') as $element) {
            $elementIds[$element->getAttribute('id')] = true;
        }

        $brokenFragmentCount = 0;

        foreach ($xpath->query('//a[starts-with(@href, "#")]') as $anchor) {
            $fragment = rawurldecode(substr($anchor->getAttribute('href'), 1));

            if ($fragment !== '' && ! isset($elementIds[$fragment])) {
                $brokenFragmentCount++;
            }
        }

        $lowercaseText = mb_strtolower($text);
        $possibleDefacementText = false;

        foreach (self::DEFACEMENT_MARKERS as $marker) {
            if (str_contains($lowercaseText, $marker)) {
                $possibleDefacementText = true;

                break;
            }
        }

        preg_match_all(self::SPAM_PATTERN, $lowercaseText.' '.mb_strtolower($title), $spamMatches);
        $securitySignals['spam_terms'] = array_values(array_unique($spamMatches[0]));

        return [[
            'url' => $url,
            'title' => $title !== '' ? $title : 'Untitled page',
            'description' => $description,
            'headings' => $headings,
            'word_count' => $wordCount,
            'keywords' => $this->topPageKeywords($pageKeywordCounts),
        ], $links, $pageKeywordCounts, [
            'mobile_viewport_configured' => $mobileViewportConfigured,
            'missing_image_alt_count' => $missingImageAltCount,
            'broken_fragment_count' => $brokenFragmentCount,
            'possible_defacement_text' => $possibleDefacementText,
        ] + $securitySignals];
    }

    /**
     * Passive indicators often seen on compromised sites. They are prompts to investigate, not proof.
     *
     * @return array{
     *     external_links: list<string>,
     *     hidden_external_links: list<string>,
     *     obfuscated_inline_scripts: int,
     *     script_hosts: list<string>,
     *     iframe_hosts: list<string>
     * }
     */
    private function securitySignals(DOMXPath $xpath, string $pageUrl): array
    {
        $pageHost = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        $externalLinks = [];
        $hiddenExternalLinks = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            $absolute = $this->absoluteHttpUrl($anchor->getAttribute('href'), $pageUrl);
            $linkHost = $absolute === null ? null : strtolower((string) parse_url($absolute, PHP_URL_HOST));

            if ($linkHost === null || $linkHost === $pageHost) {
                continue;
            }

            if (count($externalLinks) < 50) {
                $externalLinks[$absolute] = $absolute;
            }

            for ($node = $anchor; $node instanceof \DOMElement; $node = $node->parentNode) {
                if (preg_match(self::HIDDEN_STYLE_PATTERN, $node->getAttribute('style')) === 1 || $node->hasAttribute('hidden')) {
                    $hiddenExternalLinks[$linkHost] = $linkHost;

                    break;
                }
            }
        }

        $obfuscatedScripts = 0;

        foreach ($xpath->query('//script[not(@src)]') as $script) {
            if (preg_match(self::OBFUSCATED_SCRIPT_PATTERN, $script->textContent) === 1) {
                $obfuscatedScripts++;
            }
        }

        $hostsFor = function (string $query) use ($xpath, $pageUrl, $pageHost): array {
            $hosts = [];

            foreach ($xpath->query($query) as $element) {
                $absolute = $this->absoluteHttpUrl($element->getAttribute('src'), $pageUrl);
                $host = $absolute === null ? null : strtolower((string) parse_url($absolute, PHP_URL_HOST));

                if ($host !== null && $host !== $pageHost) {
                    $hosts[$host] = $host;
                }
            }

            return array_values($hosts);
        };

        return [
            'external_links' => array_values($externalLinks),
            'hidden_external_links' => array_values($hiddenExternalLinks),
            'obfuscated_inline_scripts' => $obfuscatedScripts,
            'script_hosts' => $hostsFor('//script[@src]'),
            'iframe_hosts' => $hostsFor('//iframe[@src]'),
        ];
    }

    private function absoluteHttpUrl(string $href, string $pageUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (str_starts_with($href, '//')) {
            $href = parse_url($pageUrl, PHP_URL_SCHEME).':'.$href;
        }

        $parts = $this->parseUrl($href);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $url = strtolower($parts['scheme']).'://'.strtolower($parts['host']).($parts['path'] ?? '/');

        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        return filter_var($url, FILTER_VALIDATE_URL) === false ? null : mb_substr($url, 0, 500);
    }

    /**
     * @param  array{
     *     strict_transport_security: string,
     *     content_security_policy: string,
     *     content_type_options: string,
     *     frame_options: string
     * }  $headers
     * @param  array{
     *     mobile_viewport_configured: bool,
     *     missing_image_alt_count: int,
     *     broken_fragment_count: int,
     *     possible_defacement_text: bool
     * }  $signals
     * @return list<array{code: string, severity: string, page: string, message: string}>
     */
    private function healthIssuesForPage(string $url, array $headers, array $signals): array
    {
        $issues = [];
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== 'https') {
            $issues[] = [
                'code' => 'https_not_used',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'This page uses HTTP instead of HTTPS.',
            ];
        } elseif (preg_match('/(?:^|;)\s*max-age\s*=\s*[1-9]\d*/i', $headers['strict_transport_security']) !== 1) {
            $issues[] = [
                'code' => 'missing_hsts',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'Strict-Transport-Security is missing or does not set a positive max-age.',
            ];
        }

        if (! $signals['mobile_viewport_configured']) {
            $issues[] = [
                'code' => 'mobile_viewport_missing',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'A device-width viewport setting was not found; mobile scaling may be affected.',
            ];
        }

        if ($signals['missing_image_alt_count'] > 0) {
            $issues[] = [
                'code' => 'images_missing_alt',
                'severity' => 'warning',
                'page' => $url,
                'message' => $signals['missing_image_alt_count'].' image(s) do not have alt text.',
            ];
        }

        if ($signals['broken_fragment_count'] > 0) {
            $issues[] = [
                'code' => 'broken_page_anchors',
                'severity' => 'warning',
                'page' => $url,
                'message' => $signals['broken_fragment_count'].' in-page link(s) point to an ID that was not found.',
            ];
        }

        if (trim($headers['content_security_policy']) === '') {
            $issues[] = [
                'code' => 'missing_csp',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'The Content-Security-Policy hardening header was not found.',
            ];
        }

        if (strtolower(trim($headers['content_type_options'])) !== 'nosniff') {
            $issues[] = [
                'code' => 'missing_nosniff',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'The X-Content-Type-Options: nosniff hardening header was not found.',
            ];
        }

        $frameAncestorsConfigured = preg_match(
            '/(?:^|;)\s*frame-ancestors(?:\s|;|$)/i',
            $headers['content_security_policy'],
        ) === 1;
        $frameOptionsConfigured = in_array(
            strtoupper(trim($headers['frame_options'])),
            ['DENY', 'SAMEORIGIN'],
            true,
        );

        if (! $frameOptionsConfigured && ! $frameAncestorsConfigured) {
            $issues[] = [
                'code' => 'missing_frame_protection',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'No X-Frame-Options or CSP frame-ancestors protection was found.',
            ];
        }

        if (($signals['spam_terms'] ?? []) !== []) {
            $issues[] = [
                'code' => 'spam_content',
                'severity' => 'error',
                'page' => $url,
                'message' => 'Spam wording often injected by hackers was found: '.implode(', ', array_slice($signals['spam_terms'], 0, 5)).'.',
            ];
        }

        if (($signals['hidden_external_links'] ?? []) !== []) {
            $issues[] = [
                'code' => 'hidden_external_links',
                'severity' => 'error',
                'page' => $url,
                'message' => 'Hidden links to other websites were found: '.implode(', ', array_slice($signals['hidden_external_links'], 0, 5)).'.',
            ];
        }

        if (($signals['obfuscated_inline_scripts'] ?? 0) > 0) {
            $issues[] = [
                'code' => 'obfuscated_script',
                'severity' => 'warning',
                'page' => $url,
                'message' => $signals['obfuscated_inline_scripts'].' inline script(s) use encoded or packed code that is common in injected malware.',
            ];
        }

        if ($signals['possible_defacement_text']) {
            $issues[] = [
                'code' => 'possible_defacement_text',
                'severity' => 'warning',
                'page' => $url,
                'message' => 'Possible website-defacement wording was found in visible page text; this is not proof of a compromise.',
            ];
        }

        return $issues;
    }

    /**
     * @return array<string, int>
     */
    private function countKeywords(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $matches);
        $counts = [];

        foreach ($matches[0] as $term) {
            if (preg_match('/^\p{N}+$/u', $term) === 1 || in_array($term, self::STOP_WORDS, true)) {
                continue;
            }

            $counts[$term] = ($counts[$term] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{term: string, occurrences: int}>
     */
    private function topPageKeywords(array $counts): array
    {
        arsort($counts);
        $keywords = [];

        foreach (array_slice($counts, 0, 5, true) as $term => $occurrences) {
            $keywords[] = [
                'term' => $term,
                'occurrences' => $occurrences,
            ];
        }

        return $keywords;
    }

    /**
     * @return list<string>
     */
    private function discoverLinks(DOMXPath $xpath, string $pageUrl): array
    {
        $links = [];
        $pageParts = parse_url($pageUrl);
        $host = strtolower($pageParts['host']);
        $scheme = strtolower($pageParts['scheme']);
        $port = $pageParts['port'] ?? ($scheme === 'https' ? 443 : 80);

        foreach ($xpath->query('//a[@href]') as $anchor) {
            $link = $this->resolveLink(
                $anchor->getAttribute('href'),
                $pageUrl,
                $host,
                $scheme,
                $port,
            );

            if ($link !== null) {
                $links[$link] = $link;
            }
        }

        return array_values($links);
    }

    private function resolveLink(string $href, string $pageUrl, string $host, string $scheme, int $port): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $parts = $this->parseUrl($href);

        if ($href === '' || ! is_array($parts)) {
            return null;
        }

        if (
            (isset($parts['scheme']) && strtolower($parts['scheme']) !== $scheme)
            || (isset($parts['host']) && strtolower(rtrim($parts['host'], '.')) !== $host)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== $port)
        ) {
            return null;
        }

        $currentPath = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        $path = $parts['path'] ?? $currentPath;

        if (! str_starts_with($path, '/')) {
            $basePath = str_ends_with($currentPath, '/')
                ? $currentPath
                : rtrim(dirname($currentPath), '/').'/';
            $path = $basePath.$path;
        }

        $normalizedPath = $this->normalizePath($path);
        $candidate = $scheme.'://'.$host.$normalizedPath;

        return filter_var($candidate, FILTER_VALIDATE_URL) === false ? null : $candidate;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        $hasTrailingSlash = str_ends_with($path, '/') && $path !== '/';

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalizedPath = '/'.implode('/', $segments);

        return $hasTrailingSlash && $segments !== [] ? $normalizedPath.'/' : $normalizedPath;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function parseUrl(string $url): array|false
    {
        try {
            return parse_url($url);
        } catch (\ValueError) {
            return false;
        }
    }
}
