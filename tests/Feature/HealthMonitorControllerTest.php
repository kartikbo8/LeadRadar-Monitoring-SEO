<?php

use App\Jobs\RunMonitoredSiteHealthCheck;
use App\Models\HealthCheckRun;
use App\Models\MonitoredSite;
use App\Models\User;
use App\Services\DomainOwnershipVerifier;
use App\Services\PublicHostResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('redirects guests to sign in before showing the monitoring dashboard', function () {
    $this->get(route('health-monitor.index'))
        ->assertRedirect(route('login'));
});

it('requires DNS verification before a site can be scanned', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
    ]);

    Queue::fake([RunMonitoredSiteHealthCheck::class]);

    $this->actingAs($user)
        ->post(route('health-monitor.sites.scan', $site->id))
        ->assertSessionHasErrors('monitoring');

    $this->assertDatabaseMissing('health_check_runs', [
        'monitored_site_id' => $site->id,
    ]);

    Queue::assertNothingPushed();
});

it('does not disclose or modify another user\'s monitored site', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $site = $owner->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);

    Queue::fake([RunMonitoredSiteHealthCheck::class]);

    $this->actingAs($otherUser)
        ->post(route('health-monitor.sites.scan', $site->id))
        ->assertNotFound();

    $this->assertDatabaseMissing('health_check_runs', [
        'monitored_site_id' => $site->id,
    ]);

    Queue::assertNothingPushed();
});

it('does not add a site until the user confirms monitoring authorization', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('health-monitor.sites.store'), [
            'website' => 'https://example.test/',
        ])
        ->assertSessionHasErrors('authorization');

    $this->assertDatabaseMissing('monitored_sites', [
        'user_id' => $user->id,
        'host' => 'example.test',
    ]);
});

it('creates a DNS verification record only after the user confirms authorization', function () {
    $user = User::factory()->create();

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    $this->actingAs($user)
        ->post(route('health-monitor.sites.store'), [
            'website' => 'https://example.test/products',
            'authorization' => '1',
        ])
        ->assertRedirect(route('health-monitor.index'));

    $site = MonitoredSite::query()->where('user_id', $user->id)->firstOrFail();
    $storedToken = DB::table('monitored_sites')->where('id', $site->id)->value('verification_token');

    expect($site->website_url)->toBe('https://example.test/')
        ->and($site->verified_at)->toBeNull()
        ->and($site->is_active)->toBeFalse()
        ->and(array_key_exists('verification_token', $site->toArray()))->toBeFalse()
        ->and($storedToken)->not->toBe($site->verification_token);

    $this->assertDatabaseHas('monitored_sites', [
        'id' => $site->id,
        'host' => 'example.test',
    ]);

    $this->actingAs($user)
        ->get(route('health-monitor.index'))
        ->assertOk()
        ->assertSee('leadradar-verification='.$site->verification_token);
});

it('verifies a DNS token and enables daily monitoring', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(DomainOwnershipVerifier::class)
        ->shouldReceive('verify')
        ->once()
        ->with('example.test', 'verification-token')
        ->andReturn(true);

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id))
        ->assertSessionHas('status', 'Domain verified. Daily monitoring is now enabled.');

    $this->assertDatabaseHas('monitored_sites', [
        'id' => $site->id,
        'is_active' => true,
    ]);
    expect($site->fresh()->verified_at)->not->toBeNull();
});

it('queues an immediate check for an owned verified domain', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);

    Queue::fake([RunMonitoredSiteHealthCheck::class]);

    $this->actingAs($user)
        ->post(route('health-monitor.sites.scan', $site->id))
        ->assertSessionHas('status', 'Website health check queued.');

    $run = HealthCheckRun::query()->where('monitored_site_id', $site->id)->firstOrFail();

    expect($run->status)->toBe('queued')
        ->and($run->scheduled_for)->toBeNull();

    Queue::assertPushed(
        RunMonitoredSiteHealthCheck::class,
        fn (RunMonitoredSiteHealthCheck $job): bool => $job->monitoredSiteId === $site->id
            && $job->healthCheckRunId === $run->id,
    );
});

it('shows saved findings without rendering page errors as HTML', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $site->runs()->create([
        'status' => 'completed',
        'scanned_at' => now(),
        'results' => [
            'pages_scanned' => 1,
            'browser' => [
                'pages' => [['url' => 'https://example.test/', 'viewport' => 'mobile']],
                'blocked_external_requests' => 0,
            ],
            'issues' => [[
                'code' => 'browser_javascript_error',
                'severity' => 'warning',
                'page' => 'https://example.test/',
                'device' => 'mobile',
                'message' => '<script>alert(1)</script>',
            ]],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('health-monitor.index'))
        ->assertOk()
        ->assertSeeText('1 page crawled')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('schedules one daily check for each verified active site', function () {
    $this->travelTo(now()->startOfDay());
    $scheduledDate = now()->toDateString();
    $user = User::factory()->create();
    $activeSite = $user->monitoredSites()->create([
        'website_url' => 'https://active.example.test/',
        'host' => 'active.example.test',
        'verification_token' => 'active-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $pausedSite = $user->monitoredSites()->create([
        'website_url' => 'https://paused.example.test/',
        'host' => 'paused.example.test',
        'verification_token' => 'paused-token',
        'verified_at' => now(),
        'is_active' => false,
    ]);

    Queue::fake([RunMonitoredSiteHealthCheck::class]);

    $this->artisan('schedule:run')->assertExitCode(0);
    $this->travelBack();

    $scheduledRun = HealthCheckRun::query()
        ->where('monitored_site_id', $activeSite->id)
        ->whereDate('scheduled_for', $scheduledDate)
        ->firstOrFail();

    expect($scheduledRun->status)->toBe('queued');

    $this->assertDatabaseMissing('health_check_runs', [
        'monitored_site_id' => $pausedSite->id,
        'scheduled_for' => $scheduledDate,
    ]);

    Queue::assertPushed(
        RunMonitoredSiteHealthCheck::class,
        fn (RunMonitoredSiteHealthCheck $job): bool => $job->monitoredSiteId === $activeSite->id
            && $job->healthCheckRunId === $scheduledRun->id,
    );
});

it('keeps verification when only the scheme of a site changes', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'http://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $site->runs()->create(['status' => 'completed', 'scanned_at' => now()]);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')->once()->with('example.test')->andReturn('93.184.216.34');

    $this->actingAs($user)
        ->patch(route('health-monitor.sites.update', $site->id), [
            'edit_website' => 'https://example.test/about',
            'authorization' => '1',
        ])
        ->assertSessionHas('status', 'Website URL updated. Domain verification is unchanged.');

    $site->refresh();

    expect($site->website_url)->toBe('https://example.test/')
        ->and($site->verified_at)->not->toBeNull()
        ->and($site->is_active)->toBeTrue()
        ->and($site->verification_token)->toBe('verification-token')
        ->and($site->runs()->count())->toBe(1);
});

it('resets verification and history when the domain of a site changes', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
        'last_scanned_at' => now(),
    ]);
    $site->runs()->create(['status' => 'completed', 'scanned_at' => now()]);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')->once()->with('other.test')->andReturn('93.184.216.34');

    $this->actingAs($user)
        ->patch(route('health-monitor.sites.update', $site->id), [
            'edit_website' => 'other.test',
            'authorization' => '1',
        ])
        ->assertSessionHasNoErrors();

    $site->refresh();

    expect($site->host)->toBe('other.test')
        ->and($site->website_url)->toBe('https://other.test/')
        ->and($site->verified_at)->toBeNull()
        ->and($site->is_active)->toBeFalse()
        ->and($site->last_scanned_at)->toBeNull()
        ->and($site->verification_token)->not->toBe('verification-token')
        ->and($site->runs()->count())->toBe(0);
});

it('rejects editing a site to a domain the user already monitors', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
    ]);
    $user->monitoredSites()->create([
        'website_url' => 'https://other.test/',
        'host' => 'other.test',
        'verification_token' => 'other-token',
    ]);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')->andReturn('93.184.216.34');

    $this->actingAs($user)
        ->patch(route('health-monitor.sites.update', $site->id), [
            'edit_website' => 'https://other.test/',
            'authorization' => '1',
        ])
        ->assertSessionHasErrors('edit_website');

    expect($site->fresh()->host)->toBe('example.test');
});

it('blocks editing while a health check is in progress', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
        'verified_at' => now(),
        'is_active' => true,
    ]);
    $site->runs()->create(['status' => 'running']);

    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')->andReturn('93.184.216.34');

    $this->actingAs($user)
        ->patch(route('health-monitor.sites.update', $site->id), [
            'edit_website' => 'https://other.test/',
            'authorization' => '1',
        ])
        ->assertSessionHasErrors('edit_website');

    expect($site->fresh()->host)->toBe('example.test');
});

it('does not let a user edit another user\'s monitored site', function () {
    $owner = User::factory()->create();
    $site = $owner->monitoredSites()->create([
        'website_url' => 'https://example.test/',
        'host' => 'example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->actingAs(User::factory()->create())
        ->patch(route('health-monitor.sites.update', $site->id), [
            'edit_website' => 'https://other.test/',
            'authorization' => '1',
        ])
        ->assertNotFound();

    expect($site->fresh()->host)->toBe('example.test');
});

it('verifies a subdomain using a TXT record on its root domain', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://www.example.test/',
        'host' => 'www.example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(DomainOwnershipVerifier::class)
        ->shouldReceive('verify')->once()->with('example.test', 'verification-token')->andReturn(true);

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id), ['record_domain' => 'example.test'])
        ->assertSessionHasNoErrors();

    expect($site->fresh()->verified_at)->not->toBeNull();
});

it('rejects a verification record domain that is not the host or its parent', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://www.example.test/',
        'host' => 'www.example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(DomainOwnershipVerifier::class)->shouldNotReceive('verify');

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id), ['record_domain' => 'attacker.test'])
        ->assertSessionHasErrors('record_domain');

    expect($site->fresh()->verified_at)->toBeNull();
});

it('does not offer public suffixes as verification record domains', function () {
    $site = new MonitoredSite(['host' => 'www.shop.co.in']);

    expect($site->verificationDomains())->toBe(['www.shop.co.in', 'shop.co.in']);
});

it('verifies a site using a homepage meta tag', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://www.example.test/',
        'host' => 'www.example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(\App\Services\WebsiteAuditService::class)
        ->shouldReceive('fetchPublicPageHtml')->once()->with('https://www.example.test/')
        ->andReturn('<html><head><meta name="LeadRadar-Verification" content=" verification-token "></head><body></body></html>');
    $this->mock(DomainOwnershipVerifier::class)->shouldNotReceive('verify');

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id), ['method' => 'meta'])
        ->assertSessionHas('status', 'Domain verified. Daily monitoring is now enabled.');

    expect($site->fresh()->verified_at)->not->toBeNull()
        ->and($site->fresh()->is_active)->toBeTrue();
});

it('does not verify when the homepage meta tag is missing or wrong', function (string $html) {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://www.example.test/',
        'host' => 'www.example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(\App\Services\WebsiteAuditService::class)
        ->shouldReceive('fetchPublicPageHtml')->once()->andReturn($html);

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id), ['method' => 'meta'])
        ->assertSessionHasErrors('verification');

    expect($site->fresh()->verified_at)->toBeNull();
})->with([
    'missing' => '<html><head></head><body>verification-token</body></html>',
    'wrong token' => '<html><head><meta name="leadradar-verification" content="other-token"></head></html>',
    'wrong name' => '<html><head><meta name="description" content="verification-token"></head></html>',
]);

it('shows a friendly error when the homepage cannot be fetched for meta verification', function () {
    $user = User::factory()->create();
    $site = $user->monitoredSites()->create([
        'website_url' => 'https://www.example.test/',
        'host' => 'www.example.test',
        'verification_token' => 'verification-token',
    ]);

    $this->mock(\App\Services\WebsiteAuditService::class)
        ->shouldReceive('fetchPublicPageHtml')->once()
        ->andThrow(new \App\Exceptions\WebsiteAuditException('This website redirects. Enter its final HTTPS address and try again.'));

    $this->actingAs($user)
        ->post(route('health-monitor.sites.verify', $site->id), ['method' => 'meta'])
        ->assertSessionHasErrors('verification');

    expect($site->fresh()->verified_at)->toBeNull();
});
