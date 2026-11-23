<?php

use App\Exceptions\WebsiteAuditException;
use App\Services\PublicHostResolver;
use Illuminate\Support\Facades\Http;

it('scans competitor page terms for free and labels them as on-page content', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Industrial packaging</title><meta name="description" content="Packaging for businesses"></head><body><h1>Industrial packaging for businesses</h1><p>We supply industrial packaging to local businesses.</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/competitors?website=example.test')
        ->assertOk()
        ->assertSeeText('Competitor page keywords')
        ->assertSeeText('industrial')
        ->assertSeeText('example.test')
        ->assertSeeText('not Google rankings')
        ->assertSee('https://www.google.com/search?q=industrial')
        ->assertSee('https://www.google.com/search?q=site%3Aexample.test+industrial')
        ->assertSeeText('Open Google results')
        ->assertSeeText('Find this site')
        ->assertSeeText('View details +')
        ->assertSee('<details', false)
        ->assertSee('https://example.test/')
        ->assertDontSeeText('DataForSEO')
        ->assertDontSeeText('Google position');

    Http::assertSentCount(1);
});

it('reuses a recent scan of the same competitor instead of crawling it again', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><head><title>Industrial packaging</title></head><body><h1>Industrial packaging</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->get('/competitors?website=example.test')->assertOk();
    $repeatSearch = $this->get('/competitors?website=https%3A%2F%2FEXAMPLE.test%2F');

    $repeatSearch->assertOk()->assertSeeText('industrial');
    Http::assertSentCount(1);
});

it('shows the scan error and retries the competitor on the next search', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->twice()
        ->with('example.test')
        ->andThrow(new WebsiteAuditException('Only publicly accessible websites can be analyzed.'));

    Http::preventStrayRequests();

    $this->get('/competitors?website=example.test')
        ->assertOk()
        ->assertSeeText('Only publicly accessible websites can be analyzed.');
    $repeatSearch = $this->get('/competitors?website=example.test');

    $repeatSearch->assertOk()->assertSeeText('Only publicly accessible websites can be analyzed.');
});

it('requires a website to search', function () {
    Http::preventStrayRequests();

    $response = $this->get('/competitors?website=');

    $response->assertSessionHasErrors(['website' => 'The website field is required.']);
});

it('compares competitor terms with your website and shows page details', function () {
    $resolver = $this->mock(PublicHostResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('competitor.test')->andReturn('93.184.216.34');
    $resolver->shouldReceive('resolve')->once()->with('mine.test')->andReturn('93.184.216.35');

    Http::preventStrayRequests();
    Http::fake([
        'competitor.test/*' => Http::response(
            '<html><head><title>Wholesale packaging</title><meta name="description" content="Cartons for factories"></head><body><h1>Industrial packaging cartons</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'mine.test/*' => Http::response(
            '<html><head><title>Shipping</title></head><body><h1>Industrial shipping</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $response = $this->get('/competitors?website=competitor.test&your_website=mine.test');

    $response->assertOk()
        ->assertSeeText('competitor.test vs mine.test')
        ->assertSeeTextInOrder(['Content gaps', 'packaging', 'Shared terms', 'industrial', 'Only on your site', 'shipping'])
        ->assertSeeTextInOrder(['Page-by-page SEO details', 'Wholesale packaging', 'Cartons for factories', 'Industrial packaging cartons']);
    Http::assertSentCount(2);
});

it('still shows the competitor scan when your website cannot be scanned', function () {
    $resolver = $this->mock(PublicHostResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('competitor.test')->andReturn('93.184.216.34');
    $resolver->shouldReceive('resolve')->once()->with('mine.test')
        ->andThrow(new WebsiteAuditException('Only publicly accessible websites can be analyzed.'));

    Http::preventStrayRequests();
    Http::fake([
        'competitor.test/*' => Http::response(
            '<html><body><h1>Industrial packaging</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $response = $this->get('/competitors?website=competitor.test&your_website=mine.test');

    $response->assertOk()
        ->assertSeeText('Your website could not be compared: Only publicly accessible websites can be analyzed.')
        ->assertSeeText('On-page keywords across competitor.test')
        ->assertDontSeeText('Content gaps');
});

it('downloads competitor keywords as a CSV file', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Http::preventStrayRequests();
    Http::fake([
        'example.test/*' => Http::response(
            '<html><body><h1>Packaging</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $response = $this->get('/competitors/export?website=example.test');

    $response->assertOk()->assertDownload('competitor-keywords-example-test.csv');
    expect($response->streamedContent())->toBe("Keyword,Occurrences,Pages,\"Page URLs\"\npackaging,1,1,https://example.test/\n");
});

it('sends the export back to the search page when the competitor cannot be scanned', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andThrow(new WebsiteAuditException('Only publicly accessible websites can be analyzed.'));

    Http::preventStrayRequests();

    $response = $this->get('/competitors/export?website=example.test');

    $response->assertRedirect(route('competitors.index', ['website' => 'example.test']));
});
