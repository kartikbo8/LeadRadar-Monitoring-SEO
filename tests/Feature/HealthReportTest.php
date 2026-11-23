<?php

use App\Exceptions\WebsiteAuditException;
use App\Models\User;
use App\Services\HealthReportBuilder;
use App\Services\LinkHealthChecker;
use App\Services\PublicHostResolver;
use App\Services\WebsiteAuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

function browserPage(array $overrides = []): array
{
    return $overrides + [
        'url' => 'https://example.test/',
        'viewport' => 'mobile',
        'response_status' => 200,
        'navigation_error' => null,
        'viewport_width' => 390,
        'document_width' => 390,
        'horizontal_overflow' => false,
        'overflow_elements' => [],
        'console_errors' => [],
        'page_errors' => [],
        'failed_requests' => [],
        'broken_resources' => [],
    ];
}

function auditResult(array $issues = [], array $scriptHosts = []): array
{
    return ['health_checks' => ['issues' => $issues, 'script_hosts' => $scriptHosts]];
}

function linkResult(array $broken = []): array
{
    return ['checked' => 5 + count($broken), 'ok' => 5, 'broken' => $broken, 'unverified' => [], 'redirects' => [], 'found' => 5 + count($broken), 'truncated' => false];
}

function tlsResult(int $days = 90): array
{
    return ['valid' => true, 'expires_at' => now()->addDays($days)->toAtomString(), 'days_remaining' => $days, 'issuer' => 'Test CA', 'error' => null];
}

it('reports responsive status per page and device with the elements that overflow', function () {
    $built = app(HealthReportBuilder::class)->build('https://example.test/', auditResult(), [
        'pages' => [
            browserPage(),
            browserPage(['viewport' => 'tablet', 'viewport_width' => 768, 'document_width' => 860, 'horizontal_overflow' => true,
                'overflow_elements' => [['element' => 'div.header-navbar', 'right' => 860]]]),
            browserPage(['viewport' => 'desktop', 'response_status' => 500]),
        ],
    ], linkResult(), tlsResult());

    $statuses = array_column($built['report']['responsive'], 'status', 'device');

    expect($statuses)->toBe(['mobile' => 'ok', 'tablet' => 'overflow', 'desktop' => 'failed'])
        ->and($built['report']['responsive'][1]['elements'])->toBe(['div.header-navbar'])
        ->and($built['report']['responsive'][1]['detail'])->toContain('92px wider')
        ->and($built['report']['summary']['responsive_ok'])->toBe(1)
        ->and(collect($built['issues'])->firstWhere('code', 'horizontal_overflow')['fix'])->toContain('max-width');
});

it('groups repeated JavaScript errors and lists broken links with where they were found', function () {
    $built = app(HealthReportBuilder::class)->build('https://example.test/', auditResult(), [
        'pages' => [
            browserPage(['page_errors' => ['menu is not defined']]),
            browserPage(['viewport' => 'desktop', 'page_errors' => ['menu is not defined'],
                'broken_resources' => [['url' => 'https://example.test/logo.png', 'type' => 'image', 'status' => 404, 'error' => null]]]),
        ],
    ], linkResult([[
        'url' => 'https://example.test/old', 'found_on' => 'https://example.test/', 'internal' => true, 'status' => 404, 'reason' => 'Page not found (HTTP 404).',
    ]]), tlsResult());

    expect($built['report']['errors'])->toHaveCount(1)
        ->and($built['report']['errors'][0]['devices'])->toBe(['mobile', 'desktop'])
        ->and($built['report']['broken_resources'][0]['status'])->toBe(404)
        ->and($built['report']['summary']['broken_links'])->toBe(1)
        ->and($built['report']['summary']['errors'])->toBe(2)
        ->and(collect($built['report']['precautions'])->pluck('code'))->toContain('broken_link');
});

it('flags hacking indicators, redirects and an expiring certificate in the security checklist', function () {
    $built = app(HealthReportBuilder::class)->build('https://example.test/', auditResult([
        ['code' => 'spam_content', 'severity' => 'error', 'page' => 'https://example.test/', 'message' => 'Spam'],
        ['code' => 'missing_csp', 'severity' => 'warning', 'page' => 'https://example.test/', 'message' => 'No CSP'],
    ], ['cdn.example.net']), [
        'pages' => [browserPage()],
        'offsite_navigations' => [['from' => 'https://example.test/', 'to' => 'https://evil.example/']],
        'external_hosts' => [['host' => 'tracker.example', 'types' => ['script']]],
    ], linkResult(), tlsResult(10));

    $security = collect($built['report']['security'])->keyBy('label');

    expect($security['Spam content (pharma, casino, etc.)']['status'])->toBe('fail')
        ->and($security['Malicious redirects']['status'])->toBe('fail')
        ->and($security['SSL certificate']['status'])->toBe('fail')
        ->and($security['Content-Security-Policy']['status'])->toBe('warn')
        ->and($security['Hidden outbound links']['status'])->toBe('pass')
        ->and($security['Third-party scripts']['detail'])->toContain('cdn.example.net, tracker.example')
        ->and($built['report']['summary']['security_failures'])->toBe(3);
});

it('checks links through pinned public addresses and classifies the responses', function () {
    $resolver = $this->mock(PublicHostResolver::class);
    $resolver->shouldReceive('resolve')->with('example.test')->andReturn('93.184.216.34');
    $resolver->shouldReceive('resolve')->with('gone.example')->andThrow(new WebsiteAuditException('Could not resolve this website domain.'));
    $resolver->shouldReceive('resolve')->with('internal.example')->andThrow(new WebsiteAuditException('Only publicly accessible websites can be analyzed.'));

    Http::preventStrayRequests();
    Http::fake([
        'example.test/ok' => Http::response('', 200),
        'example.test/missing' => Http::response('', 404),
        'example.test/moved' => Http::response('', 301, ['Location' => 'https://example.test/new']),
        'example.test/bot-wall' => Http::response('', 403),
    ]);

    $found = fn (string $url, bool $internal = true): array => ['url' => $url, 'found_on' => 'https://example.test/', 'internal' => $internal];

    $result = app(LinkHealthChecker::class)->check([
        $found('https://example.test/ok'),
        $found('https://example.test/missing'),
        $found('https://example.test/moved'),
        $found('https://example.test/bot-wall'),
        $found('https://gone.example/', false),
        $found('https://internal.example/', false),
    ]);

    expect(array_column($result['broken'], 'url'))->toBe(['https://gone.example/', 'https://example.test/missing'])
        ->and($result['broken'][1]['reason'])->toBe('Page not found (HTTP 404).')
        ->and(array_column($result['unverified'], 'url'))->toBe(['https://example.test/bot-wall'])
        ->and($result['redirects'][0]['location'])->toBe('https://example.test/new')
        ->and($result['ok'])->toBe(2);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/ok' && $request->method() === 'HEAD');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'internal.example'));
});

it('detects spam words, hidden outbound links and obfuscated scripts while crawling', function () {
    $this->mock(PublicHostResolver::class)->shouldReceive('resolve')->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Shop</title><script>eval(atob("ZG9jdW1lbnQud3JpdGUoMSk="))</script><script src="https://cdn.example.net/app.js"></script></head>'
            .'<body><p>Best viagra deals</p><div style="display: none"><a href="https://spam.example/">cheap</a></div><a href="https://partner.example/">Partner</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $audit = app(WebsiteAuditService::class)->audit('https://example.test/', includeHealthChecks: true);
    $codes = array_column($audit['health_checks']['issues'], 'code');

    expect($codes)->toContain('spam_content', 'hidden_external_links', 'obfuscated_script')
        ->and($audit['health_checks']['script_hosts'])->toBe(['cdn.example.net'])
        ->and(array_column($audit['health_checks']['links_to_check'], 'url'))->toBe(['https://spam.example/', 'https://partner.example/']);
});

it('renders the full report for the latest completed check', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $built = app(HealthReportBuilder::class)->build('https://example.test/', auditResult(), [
        'pages' => [browserPage(['horizontal_overflow' => true, 'document_width' => 407, 'overflow_elements' => [['element' => 'div.header-navbar', 'right' => 407]]])],
    ], linkResult([[
        'url' => 'https://example.test/old', 'found_on' => 'https://example.test/', 'internal' => true, 'status' => 404, 'reason' => 'Page not found (HTTP 404).',
    ]]), tlsResult());
    $site->runs()->create([
        'status' => 'completed',
        'scanned_at' => now(),
        'results' => ['pages_scanned' => 1, 'issues' => $built['issues'], 'report' => $built['report']],
    ]);

    $this->actingAs($user)
        ->get(route('health-monitor.index'))
        ->assertOk()
        ->assertSeeText('Responsive status')
        ->assertSeeText('div.header-navbar')
        ->assertSeeText('https://example.test/old')
        ->assertSeeText('Page not found (HTTP 404).')
        ->assertSeeText('Security & hacking check')
        ->assertSeeText('What to fix');
});

it('downloads a completed report as a standalone HTML file for its owner only', function () {
    $owner = User::factory()->create();
    $site = $owner->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $built = app(HealthReportBuilder::class)->build('https://example.test/', auditResult(), [
        'pages' => [browserPage()],
    ], linkResult([[
        'url' => 'https://example.test/old', 'found_on' => 'https://example.test/', 'internal' => true, 'status' => 404, 'reason' => 'Page not found (HTTP 404).',
    ]]), tlsResult());
    $run = $site->runs()->create([
        'status' => 'completed',
        'scanned_at' => now()->setDate(2026, 11, 18),
        'results' => ['pages_scanned' => 1, 'issues' => $built['issues'], 'report' => $built['report']],
    ]);
    $downloadUrl = route('health-monitor.sites.runs.download', ['siteId' => $site->id, 'runId' => $run->id]);

    $this->actingAs($owner)
        ->get(route('health-monitor.index'))
        ->assertSee($downloadUrl, false);

    $this->actingAs($owner)
        ->get($downloadUrl)
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=leadradar-health-report-example-test-2026-11-18.html')
        ->assertSeeText('Website health report')
        ->assertSeeText('https://example.test/old')
        ->assertSeeText('Security & hacking check');

    $this->actingAs(User::factory()->create())
        ->get($downloadUrl)
        ->assertNotFound();
});

it('does not download a report for a check that has not completed', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $run = $site->runs()->create(['status' => 'running']);

    $this->actingAs($user)
        ->get(route('health-monitor.sites.runs.download', ['siteId' => $site->id, 'runId' => $run->id]))
        ->assertNotFound();
});
