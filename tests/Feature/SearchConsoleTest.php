<?php

use App\Exceptions\SearchConsoleException;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function configuredGoogleSearchConsole(): void
{
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
        'services.google.redirect_uri' => 'http://localhost/search-console/callback',
    ]);
}

function validSearchConsoleToken(): array
{
    return [
        'access_token' => 'access-token',
        'created' => time(),
        'expires_in' => 3600,
    ];
}

it('shows setup steps without requiring a paid SEO provider', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
        'services.google.redirect_uri' => null,
    ]);

    $this->get('/search-console')
        ->assertOk()
        ->assertSeeText('Connect Google Search Console')
        ->assertSeeText('GOOGLE_CLIENT_ID')
        ->assertSeeText('No paid SEO API is used.');
});

it('starts read-only Google OAuth with a session-bound state value', function () {
    configuredGoogleSearchConsole();

    $response = $this->get('/search-console/connect')->assertRedirect();
    $authorizationUrl = $response->headers->get('Location');

    expect($authorizationUrl)->toContain('accounts.google.com')
        ->and($authorizationUrl)->toContain('webmasters.readonly')
        ->and($authorizationUrl)->toContain('state=');

    $response->assertSessionHas('google_search_console_oauth_state');
});

it('preserves the selected keyword and page while connecting Google', function () {
    configuredGoogleSearchConsole();
    $target = [
        'query' => 'office chairs',
        'page' => 'https://example.com/chairs',
    ];

    $searchConsoleService = $this->mock(GoogleSearchConsoleService::class);
    $searchConsoleService->shouldReceive('isConfigured')
        ->once()
        ->andReturn(true);
    $searchConsoleService->shouldReceive('authorizationUrl')
        ->once()
        ->withArgs(fn (string $state): bool => strlen($state) === 64)
        ->andReturn('https://accounts.google.com/oauth');

    $connectResponse = $this->get(route('search-console.connect', $target))
        ->assertRedirect('https://accounts.google.com/oauth')
        ->assertSessionHas('google_search_console_ranking_target', $target);

    $state = $connectResponse->getSession()->get('google_search_console_oauth_state');

    $searchConsoleService->shouldReceive('exchangeAuthorizationCode')
        ->once()
        ->with('sample-code')
        ->andReturn(validSearchConsoleToken());

    $this->get(route('search-console.callback', [
        'state' => $state,
        'code' => 'sample-code',
    ]))
        ->assertRedirect(route('search-console.index', $target))
        ->assertSessionHas('status', 'Google Search Console is connected.');
});

it('rejects an OAuth callback with a mismatched state', function () {
    $this->withSession(['google_search_console_oauth_state' => 'expected-state'])
        ->get('/search-console/callback?state=wrong-state&code=sample')
        ->assertForbidden();
});

it('stores OAuth tokens encrypted in the session', function () {
    configuredGoogleSearchConsole();
    $token = ['access_token' => 'sensitive-access-token', 'expires_in' => 3600];

    $this->mock(GoogleSearchConsoleService::class)
        ->shouldReceive('exchangeAuthorizationCode')
        ->once()
        ->with('sample-code')
        ->andReturn($token);

    $response = $this->withSession(['google_search_console_oauth_state' => 'valid-state'])
        ->get('/search-console/callback?state=valid-state&code=sample-code')
        ->assertRedirect(route('search-console.index'))
        ->assertSessionHas('status', 'Google Search Console is connected.');

    $storedToken = $response->getSession()->get('google_search_console_token');

    expect($storedToken)->toBeString()
        ->and($storedToken)->not->toContain('sensitive-access-token')
        ->and(json_decode(Crypt::decryptString($storedToken), true))->toBe($token);
});

it('shows authorized query, ranking page, and performance metrics for a selected property', function () {
    configuredGoogleSearchConsole();
    $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));

    $token = validSearchConsoleToken();
    $searchConsoleService = $this->mock(GoogleSearchConsoleService::class);
    $searchConsoleService->shouldReceive('isConfigured')
        ->once()
        ->andReturn(true);
    $searchConsoleService->shouldReceive('listSites')
        ->once()
        ->with($token)
        ->andReturn([
            'token' => $token,
            'sites' => [[
                'site_url' => 'sc-domain:example.com',
                'permission_level' => 'siteOwner',
            ]],
        ]);
    $searchConsoleService->shouldReceive('searchAnalytics')
        ->once()
        ->with($token, 'sc-domain:example.com', '2026-10-17', '2026-11-13', null, null)
        ->andReturn([
            'token' => $token,
            'summary' => [
                'clicks' => 125,
                'impressions' => 2300,
                'ctr' => 0.0543,
                'position' => 12.7,
            ],
            'rows' => [[
                'query' => 'industrial packaging supplier',
                'page' => 'https://example.com/packaging',
                'clicks' => 24,
                'impressions' => 380,
                'ctr' => 0.0632,
                'position' => 4.2,
            ]],
        ]);

    $encryptedToken = Crypt::encryptString(json_encode($token));

    $this->withSession(['google_search_console_token' => $encryptedToken])
        ->get('/search-console?site=sc-domain%3Aexample.com&range=28')
        ->assertOk()
        ->assertSeeText('industrial packaging supplier')
        ->assertSeeText('https://example.com/packaging')
        ->assertSeeText('125')
        ->assertSeeText('2,300')
        ->assertSeeText('4.2')
        ->assertSeeText('Average position is not a fixed rank');
});

it('renders a daily average-position graph for the exact query and page', function () {
    configuredGoogleSearchConsole();
    $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));

    $token = validSearchConsoleToken();
    $searchConsoleService = $this->mock(GoogleSearchConsoleService::class);
    $searchConsoleService->shouldReceive('isConfigured')->once()->andReturn(true);
    $searchConsoleService->shouldReceive('listSites')
        ->once()
        ->with($token)
        ->andReturn([
            'token' => $token,
            'sites' => [[
                'site_url' => 'sc-domain:example.com',
                'permission_level' => 'siteOwner',
            ]],
        ]);
    $searchConsoleService->shouldReceive('searchAnalytics')
        ->once()
        ->with($token, 'sc-domain:example.com', '2026-11-07', '2026-11-13', 'office chairs', 'https://example.com/chairs')
        ->andReturn([
            'token' => $token,
            'summary' => [
                'clicks' => 12,
                'impressions' => 220,
                'ctr' => 0.0545,
                'position' => 4.5,
            ],
            'rows' => [[
                'query' => 'office chairs',
                'page' => 'https://example.com/chairs',
                'clicks' => 12,
                'impressions' => 220,
                'ctr' => 0.0545,
                'position' => 4.5,
            ]],
            'trend' => [
                ['date' => '2026-11-07', 'clicks' => 2.0, 'impressions' => 40.0, 'ctr' => 0.05, 'position' => 5.2],
                ['date' => '2026-11-08', 'clicks' => 3.0, 'impressions' => 60.0, 'ctr' => 0.05, 'position' => 4.2],
                ['date' => '2026-11-09', 'clicks' => 1.0, 'impressions' => 20.0, 'ctr' => 0.05, 'position' => 3.8],
            ],
        ]);

    $this->withSession([
        'google_search_console_token' => Crypt::encryptString(json_encode($token)),
    ])
        ->get('/search-console?site=sc-domain%3Aexample.com&range=7&query=office+chairs&page=https%3A%2F%2Fexample.com%2Fchairs')
        ->assertOk()
        ->assertSeeText('Daily Google average position')
        ->assertSeeText('office chairs')
        ->assertSeeText('https://example.com/chairs')
        ->assertSeeText('11-07')
        ->assertSee('polyline points=', false)
        ->assertSeeText('Lower numbers are better');
});

it('shows an honest empty state when Search Console has no daily ranking data', function () {
    configuredGoogleSearchConsole();
    $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));

    $token = validSearchConsoleToken();
    $searchConsoleService = $this->mock(GoogleSearchConsoleService::class);
    $searchConsoleService->shouldReceive('isConfigured')->once()->andReturn(true);
    $searchConsoleService->shouldReceive('listSites')
        ->once()
        ->with($token)
        ->andReturn([
            'token' => $token,
            'sites' => [[
                'site_url' => 'sc-domain:example.com',
                'permission_level' => 'siteOwner',
            ]],
        ]);
    $searchConsoleService->shouldReceive('searchAnalytics')
        ->once()
        ->with($token, 'sc-domain:example.com', '2026-11-07', '2026-11-13', 'office chairs', 'https://example.com/chairs')
        ->andReturn([
            'token' => $token,
            'summary' => ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0],
            'rows' => [],
            'trend' => [],
        ]);

    $this->withSession([
        'google_search_console_token' => Crypt::encryptString(json_encode($token)),
    ])
        ->get('/search-console?site=sc-domain%3Aexample.com&range=7&query=office+chairs&page=https%3A%2F%2Fexample.com%2Fchairs')
        ->assertOk()
        ->assertSeeText('No daily ranking data for this page and keyword')
        ->assertDontSee('polyline points=', false);
});

it('does not request data for a property the Google account cannot access', function () {
    configuredGoogleSearchConsole();
    $token = validSearchConsoleToken();
    $searchConsoleService = $this->mock(GoogleSearchConsoleService::class);
    $searchConsoleService->shouldReceive('isConfigured')->once()->andReturn(true);
    $searchConsoleService->shouldReceive('listSites')
        ->once()
        ->with($token)
        ->andReturn([
            'token' => $token,
            'sites' => [[
                'site_url' => 'sc-domain:example.com',
                'permission_level' => 'siteOwner',
            ]],
        ]);
    $searchConsoleService->shouldNotReceive('searchAnalytics');

    $this->withSession([
        'google_search_console_token' => Crypt::encryptString(json_encode($token)),
    ])
        ->get('/search-console?site=sc-domain%3Aother.test&range=28')
        ->assertSessionHasErrors('site');
});

it('rejects a ranking target that is not an HTTP or HTTPS page URL', function () {
        $this->get('/search-console?query=chairs&page=javascript%3Aalert%281%29')
            ->assertSessionHasErrors('page');
});

it('loads Search Console query and page data from the Google API', function () {
    configuredGoogleSearchConsole();
    Http::preventStrayRequests();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
            ->push(['rows' => [[
                'clicks' => 25,
                'impressions' => 500,
                'ctr' => 0.05,
                'position' => 8.4,
            ]]])
            ->push(['rows' => [[
                'keys' => ['packaging supplier', 'https://example.com/packaging'],
                'clicks' => 10,
                'impressions' => 170,
                'ctr' => 0.0588,
                'position' => 4.6,
            ]]]),
    ]);

    $report = app(GoogleSearchConsoleService::class)->searchAnalytics(
        validSearchConsoleToken(),
        'sc-domain:example.com',
        '2026-10-01',
        '2026-10-28',
    );

    expect($report['summary']['clicks'])->toBe(25.0)
        ->and($report['rows'][0]['query'])->toBe('packaging supplier')
        ->and($report['rows'][0]['position'])->toBe(4.6)
        ->and($report['trend'])->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token')
        && ($request->data()['dimensions'] ?? null) === ['query', 'page']
        && $request->data()['rowLimit'] === 250);
    Http::assertSentCount(2);
});

it('requests daily positions filtered to the exact query and page', function () {
    configuredGoogleSearchConsole();
    Http::preventStrayRequests();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
            ->push(['rows' => [[
                'clicks' => 12,
                'impressions' => 200,
                'ctr' => 0.06,
                'position' => 4.5,
            ]]])
            ->push(['rows' => [[
                'keys' => ['office chairs', 'https://example.com/chairs'],
                'clicks' => 12,
                'impressions' => 200,
                'ctr' => 0.06,
                'position' => 4.5,
            ]]])
            ->push(['rows' => [[
                'keys' => ['2026-11-01'],
                'clicks' => 2,
                'impressions' => 30,
                'ctr' => 0.0667,
                'position' => 5.2,
            ]]]),
    ]);

    $report = app(GoogleSearchConsoleService::class)->searchAnalytics(
        validSearchConsoleToken(),
        'sc-domain:example.com',
        '2026-11-01',
        '2026-11-07',
        'office chairs',
        'https://example.com/chairs',
    );

    expect($report['trend'])->toBe([[
        'date' => '2026-11-01',
        'clicks' => 2.0,
        'impressions' => 30.0,
        'ctr' => 0.0667,
        'position' => 5.2,
    ]]);

    Http::assertSent(fn (Request $request): bool => ($request->data()['dimensions'] ?? null) === ['date']
        && ($request->data()['dimensionFilterGroups'] ?? null) === [[
            'groupType' => 'and',
            'filters' => [
                ['dimension' => 'query', 'operator' => 'equals', 'expression' => 'office chairs'],
                ['dimension' => 'page', 'operator' => 'equals', 'expression' => 'https://example.com/chairs'],
            ],
        ]]);
    Http::assertSentCount(3);
});

it('surfaces Search Console permission errors without hiding them', function () {
    configuredGoogleSearchConsole();
    Http::preventStrayRequests();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response([], 403),
    ]);

    expect(fn () => app(GoogleSearchConsoleService::class)->listSites(validSearchConsoleToken()))
        ->toThrow(SearchConsoleException::class, 'Google denied access to this Search Console property.');
});
