<?php

use App\Jobs\RunMonitoredSiteHealthCheck;
use App\Models\User;
use App\Services\PlaywrightResponsiveAuditService;
use App\Services\PublicHostResolver;
use App\Services\TlsCertificateChecker;
use App\Services\WebsiteAuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

uses(LazilyRefreshDatabase::class);

it('stores passive and browser results for a verified site', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $run = $site->runs()->create(['status' => 'queued']);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->twice()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Office chairs</title></head><body><p>Office chairs for business websites.</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    Process::preventStrayProcesses();
    Process::fake([
        '*health-audit.mjs*' => Process::result(
            output: json_encode([
                'pages' => [[
                    'url' => 'https://example.test/',
                    'viewport' => 'mobile',
                    'response_status' => 200,
                    'navigation_error' => null,
                    'title' => 'Office chairs',
                    'viewport_width' => 390,
                    'document_width' => 420,
                    'horizontal_overflow' => true,
                    'console_errors' => [],
                    'page_errors' => [],
                    'failed_requests' => [],
                ]],
                'blocked_external_requests' => 1,
            ], JSON_THROW_ON_ERROR),
        ),
    ]);

    $this->mock(TlsCertificateChecker::class)->shouldReceive('check')->andReturn([
        'valid' => true, 'expires_at' => now()->addDays(90)->toAtomString(), 'days_remaining' => 90, 'issuer' => 'Test CA', 'error' => null,
    ]);

    (new RunMonitoredSiteHealthCheck($site->id, $run->id))->handle(
        app(WebsiteAuditService::class),
        app(PlaywrightResponsiveAuditService::class),
    );

    $completedRun = $run->fresh();
    $issues = $completedRun->results['issues'];

    expect($completedRun->status)->toBe('completed')
        ->and($completedRun->results['browser']['pages'][0]['horizontal_overflow'])->toBeTrue()
        ->and(collect($issues)->contains(fn (array $issue): bool => $issue['code'] === 'horizontal_overflow'))->toBeTrue()
        ->and($site->fresh()->last_scanned_at)->not->toBeNull();
});

it('records a failed run when Chromium cannot start', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
    ]);
    $run = $site->runs()->create(['status' => 'queued']);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->twice()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Website</title></head><body><p>Public website content</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    Process::fake([
        '*health-audit.mjs*' => Process::result(exitCode: 1),
    ]);

    $this->mock(TlsCertificateChecker::class)->shouldReceive('check')->andReturn([
        'valid' => true, 'expires_at' => now()->addDays(90)->toAtomString(), 'days_remaining' => 90, 'issuer' => 'Test CA', 'error' => null,
    ]);

    (new RunMonitoredSiteHealthCheck($site->id, $run->id))->handle(
        app(WebsiteAuditService::class),
        app(PlaywrightResponsiveAuditService::class),
    );

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->error)->toContain('Playwright Chromium browser are installed');
});
