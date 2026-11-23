<?php

use App\Exceptions\WebsiteAuditException;
use App\Services\PublicHostResolver;
use Illuminate\Support\Facades\Http;

it('shows on-page keywords and metadata from same-domain pages', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<!doctype html><html><head><title>Office chairs</title><meta name="description" content="Office chairs for local businesses"></head><body><h1>Office chairs for offices</h1><h2>&lt;script&gt;alert(1)&lt;/script&gt;</h2><p>We make office chairs, office desks, and furniture for businesses.</p><a href="/about/">About us</a><a href="https://outside.test/">Outside site</a></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $response = $this->get('/companies?website=example.test');

    $response->assertOk()
        ->assertSeeText('Domain overview')
        ->assertSeeText('example.test')
        ->assertSeeText('Top on-page keywords')
        ->assertSeeText('office')
        ->assertSeeText('Office chairs for local businesses')
        ->assertSeeText('Scanned pages')
        ->assertSeeText('Top 5 keywords found on this page')
        ->assertSeeText('Click a keyword to view its details')
        ->assertSeeText('View ranking graph in Search Console')
        ->assertSeeText('Found on these pages')
        ->assertSeeText('This scan shows which keywords appear on each page. It does not show Google rankings.')
        ->assertDontSeeText('Passive website health check')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('https://www.google.com/search', false)
        ->assertDontSeeText('outside.test');

    Http::assertSentCount(2);
});

it('does not expose website health checks through the public keyword scan', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/broken/*' => Http::response('', 404, ['Content-Type' => 'text/html']),
        'example.test/*' => Http::response(
            '<html><head><title>Office chairs</title></head><body><p>Office chairs and furniture. Hacked by unknown group.</p><a href="/broken/">Broken page</a><a href="#missing">Broken section</a><img src="/chair.jpg"></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/companies?website=example.test&health_check=1')
        ->assertOk()
        ->assertSeeText('Monitor an authorized website')
        ->assertDontSeeText('Passive website health check')
        ->assertDontSeeText('health_check');

    Http::assertSentCount(2);
});

it('shows every matching page for a keyword even when it is not among that page\'s top five', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/about/*' => Http::response(
            '<html><head><title>About storage</title></head><body><p>storage storage storage wardrobes wardrobes wardrobes furniture furniture furniture desks desks desks tables tables tables chairs</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'example.test/*' => Http::response(
            '<html><head><title>Office chairs</title></head><body><p>chairs chairs chairs chairs chairs chairs</p><a href="/about/">About</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/companies?website=example.test')
        ->assertOk()
        ->assertSee('href="#scanned-page-1"', false)
        ->assertSeeText('About storage')
        ->assertSeeText('1 occurrence')
        ->assertSeeText('View ranking graph')
        ->assertSee(route('search-console.index', [
            'query' => 'chairs',
            'page' => 'https://example.test/about/',
        ]));
});

it('reports same-domain pages that cannot be scanned', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/blocked/' => Http::response('', 503),
        'example.test/*' => Http::response(
            '<html><head><title>Website</title></head><body><p>Useful website content for visitors.</p><a href="/blocked/">Blocked page</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/companies?website=example.test')
        ->assertOk()
        ->assertSeeText('1 internal page was unreachable and skipped.');
});

it('limits each audit to ten same-domain pages', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    $links = implode('', array_map(
        static fn (int $page): string => '<a href="/page-'.$page.'">Page '.$page.'</a>',
        range(1, 12),
    ));

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Website</title></head><body><p>Useful content for website visitors.</p>'.$links.'</body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/companies?website=example.test')
        ->assertOk();

    Http::assertSentCount(10);
});

it('rejects local addresses without making an outbound request', function () {
    Http::preventStrayRequests();

    $this->get('/companies?website=http%3A%2F%2F127.0.0.1')
        ->assertOk()
        ->assertSeeText('Enter a public website domain using standard HTTP or HTTPS.');
});

it('blocks domains that resolve to private network addresses', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andThrow(new WebsiteAuditException('Only publicly accessible websites can be analyzed.'));

    Http::preventStrayRequests();

    $this->get('/companies?website=https%3A%2F%2Fexample.test')
        ->assertOk()
        ->assertSeeText('Only publicly accessible websites can be analyzed.');
});
