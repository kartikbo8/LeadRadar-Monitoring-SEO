<?php

namespace App\Services;

use App\Support\HealthIssueAdvice;

/**
 * Turns raw crawl, browser, link and certificate results into the report shown on the
 * Health Monitor page: responsive status, broken links, errors, security checks and fixes.
 */
class HealthReportBuilder
{
    public const VERSION = 2;

    private const SSL_WARNING_DAYS = 30;

    private const SSL_CRITICAL_DAYS = 14;

    /**
     * @param  array<string, mixed>  $audit  WebsiteAuditService::audit() with health checks
     * @param  array<string, mixed>  $browser  PlaywrightResponsiveAuditService::scan()
     * @param  array<string, mixed>  $links  LinkHealthChecker::check()
     * @param  array<string, mixed>  $tls  TlsCertificateChecker::check()
     * @return array{issues: list<array<string, mixed>>, report: array<string, mixed>}
     */
    public function build(string $websiteUrl, array $audit, array $browser, array $links, array $tls): array
    {
        $issues = $audit['health_checks']['issues'];
        $responsive = [];
        $errors = [];
        $brokenResources = [];

        foreach ($browser['pages'] as $page) {
            $url = $page['url'];
            $device = $page['viewport'];
            $status = is_int($page['response_status'] ?? null) ? $page['response_status'] : null;

            if (($page['navigation_error'] ?? null) !== null) {
                $responsive[] = ['url' => $url, 'device' => $device, 'status' => 'failed', 'detail' => 'Did not load: '.$page['navigation_error'], 'elements' => []];
                $issues[] = $this->issue('browser_navigation_failed', 'error', $url, 'The page did not finish loading: '.$page['navigation_error'], $device);
            } elseif ($status !== null && $status >= 400) {
                $responsive[] = ['url' => $url, 'device' => $device, 'status' => 'failed', 'detail' => 'Returned HTTP '.$status, 'elements' => []];
                $issues[] = $this->issue('browser_http_error', 'error', $url, 'The page returned HTTP '.$status.'.', $device);
            } elseif ($page['horizontal_overflow'] ?? false) {
                $extra = max(0, (int) ($page['document_width'] ?? 0) - (int) ($page['viewport_width'] ?? 0));
                $elements = array_column($page['overflow_elements'] ?? [], 'element');
                $responsive[] = [
                    'url' => $url,
                    'device' => $device,
                    'status' => 'overflow',
                    'detail' => 'Content is '.$extra.'px wider than the '.($page['viewport_width'] ?? '?').'px screen, so the page scrolls sideways.',
                    'elements' => $elements,
                ];
                $issues[] = $this->issue(
                    'horizontal_overflow',
                    'warning',
                    $url,
                    'Content is '.$extra.'px wider than the '.$device.' screen'.($elements !== [] ? ' (caused by: '.implode(', ', array_slice($elements, 0, 3)).')' : '').'.',
                    $device,
                );
            } else {
                $responsive[] = ['url' => $url, 'device' => $device, 'status' => 'ok', 'detail' => 'Fits the screen', 'elements' => []];
            }

            foreach (array_unique(array_merge($page['page_errors'] ?? [], $page['console_errors'] ?? [])) as $message) {
                $errors[$message] ??= ['message' => $message, 'pages' => [], 'devices' => []];
                $errors[$message]['pages'][$url] = $url;
                $errors[$message]['devices'][$device] = $device;
            }

            foreach ($page['broken_resources'] ?? [] as $resource) {
                $key = $resource['url'];
                $brokenResources[$key] ??= $resource + ['pages' => []];
                $brokenResources[$key]['pages'][$url] = $url;
            }
        }

        foreach ($errors as $error) {
            $issues[] = $this->issue('browser_javascript_error', 'warning', array_values($error['pages'])[0], 'JavaScript error: '.$error['message'], implode(', ', $error['devices']));
        }

        foreach ($brokenResources as $resource) {
            $reason = $resource['status'] !== null ? 'HTTP '.$resource['status'] : $resource['error'];
            $issues[] = $this->issue('browser_resource_failed', 'warning', array_values($resource['pages'])[0], 'A '.$resource['type'].' failed to load ('.$reason.'): '.$resource['url']);
        }

        foreach ($links['broken'] as $link) {
            $issues[] = $this->issue('broken_link', 'error', $link['found_on'], $link['reason'].' Link: '.$link['url']);
        }

        foreach ($browser['offsite_navigations'] ?? [] as $navigation) {
            $issues[] = $this->issue('offsite_redirect', 'error', $navigation['from'], 'The page tried to navigate the visitor to '.$navigation['to'].'.');
        }

        if (! $tls['valid']) {
            $issues[] = $this->issue('ssl_invalid', 'error', $websiteUrl, $tls['error']);
        } elseif ($tls['days_remaining'] !== null && $tls['days_remaining'] < self::SSL_WARNING_DAYS) {
            $issues[] = $this->issue('ssl_expiring', $tls['days_remaining'] < self::SSL_CRITICAL_DAYS ? 'error' : 'warning', $websiteUrl, 'The SSL certificate expires in '.$tls['days_remaining'].' day(s).');
        }

        $issues = array_map(fn (array $issue): array => $issue + ['fix' => HealthIssueAdvice::fix($issue['code'])], $issues);
        $security = $this->securityChecks($issues, $tls, $audit, $browser);
        $okRenders = count(array_filter($responsive, fn (array $row): bool => $row['status'] === 'ok'));
        $pagesTested = array_unique(array_column($responsive, 'url'));
        $pagesWithProblems = array_unique(array_column(array_filter($responsive, fn (array $row): bool => $row['status'] !== 'ok'), 'url'));

        return [
            'issues' => $issues,
            'report' => [
                'version' => self::VERSION,
                'summary' => [
                    'responsive_ok' => $okRenders,
                    'responsive_total' => count($responsive),
                    'pages_tested' => count($pagesTested),
                    'pages_responsive' => count($pagesTested) - count($pagesWithProblems),
                    'broken_links' => count($links['broken']),
                    'links_checked' => $links['checked'],
                    'errors' => count($errors) + count($brokenResources),
                    'security_problems' => count(array_filter($security, fn (array $check): bool => in_array($check['status'], ['fail', 'warn'], true))),
                    'security_failures' => count(array_filter($security, fn (array $check): bool => $check['status'] === 'fail')),
                ],
                'responsive' => $responsive,
                'links' => $links,
                'errors' => array_values(array_map(fn (array $error): array => [
                    'message' => $error['message'],
                    'pages' => array_values($error['pages']),
                    'devices' => array_values($error['devices']),
                ], $errors)),
                'broken_resources' => array_values(array_map(fn (array $resource): array => [
                    'url' => $resource['url'],
                    'type' => $resource['type'],
                    'status' => $resource['status'],
                    'error' => $resource['error'],
                    'pages' => array_values($resource['pages']),
                ], $brokenResources)),
                'security' => $security,
                'precautions' => $this->precautions($issues),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @param  array<string, mixed>  $tls
     * @param  array<string, mixed>  $audit
     * @param  array<string, mixed>  $browser
     * @return list<array{label: string, status: string, detail: string, fix: string|null}>
     */
    private function securityChecks(array $issues, array $tls, array $audit, array $browser): array
    {
        $codes = array_count_values(array_column($issues, 'code'));
        $has = fn (string $code): bool => isset($codes[$code]);
        $check = fn (string $label, bool $problem, string $problemStatus, string $okDetail, string $problemDetail, string $code): array => [
            'label' => $label,
            'status' => $problem ? $problemStatus : 'pass',
            'detail' => $problem ? $problemDetail : $okDetail,
            'fix' => $problem ? HealthIssueAdvice::fix($code) : null,
        ];

        $sslStatus = match (true) {
            ! $tls['valid'] => 'fail',
            $tls['days_remaining'] < self::SSL_CRITICAL_DAYS => 'fail',
            $tls['days_remaining'] < self::SSL_WARNING_DAYS => 'warn',
            default => 'pass',
        };

        $checks = [
            $check('HTTPS everywhere', $has('https_not_used'), 'fail', 'All scanned pages use HTTPS.', 'Some pages load over plain HTTP.', 'https_not_used'),
            [
                'label' => 'SSL certificate',
                'status' => $sslStatus,
                'detail' => $tls['valid']
                    ? 'Valid until '.date('M j, Y', strtotime($tls['expires_at'])).' ('.$tls['days_remaining'].' days left)'.($tls['issuer'] ? ', issued by '.$tls['issuer'] : '').'.'
                    : $tls['error'],
                'fix' => match ($sslStatus) {
                    'pass' => null,
                    'warn' => HealthIssueAdvice::fix('ssl_expiring'),
                    default => HealthIssueAdvice::fix($tls['valid'] ? 'ssl_expiring' : 'ssl_invalid'),
                },
            ],
            $check('Hacked / defacement text', $has('possible_defacement_text'), 'fail', 'No "hacked by" style messages found.', 'Wording typical of a defaced site was found.', 'possible_defacement_text'),
            $check('Spam content (pharma, casino, etc.)', $has('spam_content'), 'fail', 'No spam keywords found in page text.', 'Spam keywords were found; see findings below.', 'spam_content'),
            $check('Hidden outbound links', $has('hidden_external_links'), 'fail', 'No hidden links to other sites found.', 'Hidden links to other sites were found.', 'hidden_external_links'),
            $check('Malicious redirects', $has('offsite_redirect'), 'fail', 'No page tried to send visitors to another site.', 'A page tried to redirect visitors to another site.', 'offsite_redirect'),
            $check('Encoded / packed scripts', $has('obfuscated_script'), 'warn', 'No obfuscated inline scripts found.', 'Inline scripts with encoded code were found.', 'obfuscated_script'),
            $check('HSTS (force HTTPS)', $has('missing_hsts'), 'warn', 'Strict-Transport-Security header is set.', 'Strict-Transport-Security header is missing.', 'missing_hsts'),
            $check('Clickjacking protection', $has('missing_frame_protection'), 'warn', 'Frame protection header is set.', 'No X-Frame-Options or frame-ancestors rule.', 'missing_frame_protection'),
            $check('Content-Security-Policy', $has('missing_csp'), 'warn', 'A Content-Security-Policy header is set.', 'No Content-Security-Policy header.', 'missing_csp'),
            $check('MIME sniffing protection', $has('missing_nosniff'), 'warn', 'X-Content-Type-Options: nosniff is set.', 'X-Content-Type-Options: nosniff is missing.', 'missing_nosniff'),
        ];

        $scriptHosts = $audit['health_checks']['script_hosts'] ?? [];

        foreach ($browser['external_hosts'] ?? [] as $external) {
            if (in_array('script', $external['types'], true)) {
                $scriptHosts[] = $external['host'];
            }
        }

        $scriptHosts = array_values(array_unique($scriptHosts));
        sort($scriptHosts);

        $checks[] = [
            'label' => 'Third-party scripts',
            'status' => 'info',
            'detail' => $scriptHosts === []
                ? 'No scripts are loaded from other domains.'
                : count($scriptHosts).' outside domain(s) run code on your site: '.implode(', ', $scriptHosts).'.',
            'fix' => $scriptHosts === [] ? null : 'Check that you recognise every domain in this list. An unknown script domain can mean injected code; remove anything your team did not add.',
        ];

        return $checks;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array{code: string, title: string, fix: string, count: int, severity: string}>
     */
    private function precautions(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            if ($issue['fix'] === null) {
                continue;
            }

            $grouped[$issue['code']] ??= [
                'code' => $issue['code'],
                'title' => HealthIssueAdvice::title($issue['code']),
                'fix' => $issue['fix'],
                'count' => 0,
                'severity' => 'warning',
            ];
            $grouped[$issue['code']]['count']++;

            if ($issue['severity'] === 'error') {
                $grouped[$issue['code']]['severity'] = 'error';
            }
        }

        usort($grouped, fn (array $a, array $b): int => [$a['severity'] === 'error' ? 0 : 1, -$a['count']] <=> [$b['severity'] === 'error' ? 0 : 1, -$b['count']]);

        return $grouped;
    }

    /**
     * @return array{code: string, severity: string, page: string, message: string, device?: string}
     */
    private function issue(string $code, string $severity, string $page, string $message, ?string $device = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'page' => $page, 'message' => $message];

        if ($device !== null) {
            $issue['device'] = $device;
        }

        return $issue;
    }
}
