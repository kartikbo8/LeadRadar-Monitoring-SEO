<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Competitor Page Keywords | LeadRadar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:flex lg:flex-col">
            <a href="{{ route('search-console.index') }}" class="flex h-16 items-center gap-3 border-b border-slate-100 px-5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 text-sm font-bold text-white">LR</span>
                <span class="text-base font-bold tracking-tight text-slate-950">LeadRadar</span>
            </a>
            <nav aria-label="SEO tools" class="flex-1 space-y-1 px-3 py-5 text-sm">
                <a href="{{ route('search-console.index') }}" class="block rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">My Google rankings</a>
                <a href="{{ route('competitors.index') }}" aria-current="page" class="block rounded-lg bg-indigo-50 px-3 py-2.5 font-semibold text-indigo-700">Competitor page keywords</a>
                <a href="{{ route('companies.index') }}" class="block rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">Website audit</a>
            </nav>
            <div class="border-t border-slate-100 p-4">
                <p class="text-xs leading-5 text-slate-500">Free public-page scan. Search positions are not available for competitor sites.</p>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1440px] px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Free SEO research</p>
                            <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950 sm:text-2xl">Competitor page keywords</h1>
                        </div>
                        <a href="{{ route('search-console.index') }}" class="hidden rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 sm:inline-flex">
                            My Google rankings
                        </a>
                    </div>
                    <nav aria-label="Mobile SEO tools" class="mt-4 flex gap-2 overflow-x-auto pb-1 text-sm lg:hidden">
                        <a href="{{ route('search-console.index') }}" class="shrink-0 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">My Google rankings</a>
                        <a href="{{ route('competitors.index') }}" aria-current="page" class="shrink-0 rounded-lg bg-indigo-50 px-3 py-2 font-semibold text-indigo-700">Competitor page keywords</a>
                        <a href="{{ route('companies.index') }}" class="shrink-0 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Website audit</a>
                    </nav>
                </div>
            </header>

            <main class="mx-auto grid max-w-[1440px] gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="search-heading">
                    <div class="border-b border-slate-100 bg-gradient-to-br from-indigo-50 via-white to-white px-5 py-6 sm:px-7">
                        <span class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-white px-3 py-1 text-xs font-semibold text-indigo-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            No paid SEO API required
                        </span>
                        <h2 id="search-heading" class="mt-4 text-lg font-semibold text-slate-950 sm:text-xl">See which terms appear on a competitor's pages</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Scan publicly accessible pages to find visible on-page terms, titles, headings, and the pages where those terms appear.
                        </p>
                    </div>

                    <div class="p-5 sm:p-7">
                        <form method="GET" action="{{ route('competitors.index') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div class="min-w-0">
                                <label for="website" class="mb-1.5 block text-sm font-medium text-slate-700">Competitor website</label>
                                <input
                                    id="website"
                                    type="text"
                                    name="website"
                                    value="{{ old('website', $competitor) }}"
                                    placeholder="https://example.com"
                                    autocomplete="url"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                >
                                @error('website')
                                    <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="min-w-0">
                                <label for="your_website" class="mb-1.5 block text-sm font-medium text-slate-700">Your website <span class="font-normal text-slate-500">(optional, to compare)</span></label>
                                <input
                                    id="your_website"
                                    type="text"
                                    name="your_website"
                                    value="{{ old('your_website', $ownWebsite) }}"
                                    placeholder="https://your-site.com"
                                    autocomplete="url"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                >
                                @error('your_website')
                                    <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="self-end">
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200 sm:w-auto">
                                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="7"></circle>
                                        <path stroke-linecap="round" d="m20 20-4-4"></path>
                                    </svg>
                                    Scan pages
                                </button>
                            </div>
                        </form>

                        <div role="note" class="mt-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                            <svg aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path stroke-linecap="round" d="M12 11v5m0-8h.01"></path>
                            </svg>
                            <p><strong>Important:</strong> these are terms found in page content, not Google rankings, search volume, or traffic estimates. Google ranking data is available only for your own verified property through Search Console.</p>
                        </div>
                    </div>
                </section>

                @if($searchError)
                    <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">{{ $searchError }}</div>
                @endif

                @if($audit)
                    <section class="grid gap-4 sm:grid-cols-3" aria-label="Competitor page scan summary">
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Pages scanned</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($audit['pages_scanned']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Up to 10 public pages</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">On-page terms found</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format(count($audit['keywords'])) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Unique terms in visible page text</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Missing descriptions</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($audit['missing_descriptions']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Scanned pages without a meta description</p>
                        </article>
                    </section>

                    @if($audit['pages_skipped'] > 0)
                        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ $audit['pages_skipped'] }} internal {{ $audit['pages_skipped'] === 1 ? 'page was' : 'pages were' }} unreachable and skipped.
                        </div>
                    @endif

                    @if($ownSiteError)
                        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">
                            Your website could not be compared: {{ $ownSiteError }}
                        </div>
                    @endif

                    @if($comparison)
                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="comparison-heading">
                            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                                <h2 id="comparison-heading" class="text-lg font-semibold text-slate-950">{{ $audit['domain'] }} vs {{ $ownAudit['domain'] }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Each site's top terms checked against every term found on the other site's scanned pages</p>
                            </div>
                            <div class="grid divide-y divide-slate-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                                <div class="p-5 sm:p-6">
                                    <h3 class="text-sm font-semibold text-rose-700">Content gaps · {{ count($comparison['missing']) }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Competitor's top terms that do not appear on your scanned pages</p>
                                    @if($comparison['missing'] !== [])
                                        <ul class="mt-3 grid gap-1.5 text-sm">
                                            @foreach($comparison['missing'] as $keyword)
                                                <li class="flex items-center justify-between gap-3 rounded-lg bg-rose-50 px-3 py-2">
                                                    <span class="font-medium text-slate-900">{{ $keyword['term'] }}</span>
                                                    <span class="shrink-0 text-xs text-slate-600">{{ number_format($keyword['competitor_occurrences']) }} on theirs</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 text-sm text-slate-600">No gaps: your pages use all of their top terms.</p>
                                    @endif
                                </div>
                                <div class="p-5 sm:p-6">
                                    <h3 class="text-sm font-semibold text-indigo-700">Shared terms · {{ count($comparison['shared']) }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Competitor's top terms that also appear on your pages</p>
                                    @if($comparison['shared'] !== [])
                                        <ul class="mt-3 grid gap-1.5 text-sm">
                                            @foreach($comparison['shared'] as $keyword)
                                                <li class="flex items-center justify-between gap-3 rounded-lg bg-indigo-50 px-3 py-2">
                                                    <span class="font-medium text-slate-900">{{ $keyword['term'] }}</span>
                                                    <span class="shrink-0 text-xs text-slate-600">{{ number_format($keyword['competitor_occurrences']) }} theirs · {{ number_format($keyword['own_occurrences']) }} yours</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 text-sm text-slate-600">None of their top terms appear on your pages.</p>
                                    @endif
                                </div>
                                <div class="p-5 sm:p-6">
                                    <h3 class="text-sm font-semibold text-emerald-700">Only on your site · {{ count($comparison['own_only']) }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Your top terms that do not appear on their scanned pages</p>
                                    @if($comparison['own_only'] !== [])
                                        <ul class="mt-3 grid gap-1.5 text-sm">
                                            @foreach($comparison['own_only'] as $keyword)
                                                <li class="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2">
                                                    <span class="font-medium text-slate-900">{{ $keyword['term'] }}</span>
                                                    <span class="shrink-0 text-xs text-slate-600">{{ number_format($keyword['own_occurrences']) }} on yours</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 text-sm text-slate-600">They use all of your top terms too.</p>
                                    @endif
                                </div>
                            </div>
                            <div class="border-t border-slate-100 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6">
                                Based on up to 10 scanned pages per site. A term missing from the scanned pages may still appear elsewhere on the site.
                            </div>
                        </section>
                    @endif

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="keywords-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <h2 id="keywords-heading" class="text-lg font-semibold text-slate-950">On-page keywords across {{ $audit['domain'] }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Open a Google search for any term, or expand its counts to see page-level occurrences</p>
                            </div>
                            @if($audit['keywords'] !== [])
                                <a href="{{ route('competitors.export', ['website' => $competitor]) }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"></path>
                                    </svg>
                                    Download CSV
                                </a>
                            @endif
                        </div>

                        @if($audit['keywords'] !== [])
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[680px] text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th scope="col" class="px-5 py-3 sm:px-6">Keyword · click to search Google</th>
                                            <th scope="col" class="px-5 py-3 sm:px-6">Occurrences and page breakdown</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($audit['keywords'] as $keyword)
                                            @php
                                                $googleSearchUrl = 'https://www.google.com/search?q='.urlencode($keyword['term']);
                                                $siteSearchUrl = 'https://www.google.com/search?q='.urlencode('site:'.$audit['domain'].' '.$keyword['term']);
                                            @endphp
                                            <tr class="align-top transition hover:bg-slate-50/70">
                                                <td class="max-w-sm px-5 py-4 sm:px-6">
                                                    <a href="{{ $googleSearchUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-700 hover:underline">
                                                        {{ $keyword['term'] }}
                                                    </a>
                                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                        <a href="{{ $googleSearchUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-600 hover:text-indigo-700 hover:underline">Open Google results</a>
                                                        <a href="{{ $siteSearchUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-600 hover:text-indigo-700 hover:underline">Find this site</a>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4 sm:px-6">
                                                    <details class="group">
                                                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-2 gap-y-1 rounded-lg px-2 py-1 text-sm text-slate-700 transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                            <span class="font-semibold text-slate-950">{{ number_format($keyword['occurrences']) }} occurrences</span>
                                                            <span class="text-slate-500">across {{ number_format($keyword['pages']) }} pages</span>
                                                            <span class="text-xs font-semibold text-indigo-700 group-open:hidden">View details +</span>
                                                            <span class="hidden text-xs font-semibold text-indigo-700 group-open:inline">Hide details −</span>
                                                        </summary>
                                                        <ul class="mt-3 grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                            @foreach($keyword['page_details'] as $page)
                                                                <li class="flex min-w-0 items-start justify-between gap-3">
                                                                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer" class="group/link min-w-0">
                                                                        <span class="block truncate text-sm font-medium text-indigo-700 group-hover/link:underline">{{ $page['title'] !== '' ? $page['title'] : $page['url'] }}</span>
                                                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $page['url'] }}</span>
                                                                    </a>
                                                                    <span class="shrink-0 rounded-md bg-white px-2 py-1 text-xs font-semibold text-slate-700">{{ number_format($page['occurrences']) }} {{ $page['occurrences'] === 1 ? 'occurrence' : 'occurrences' }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </details>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="px-5 py-12 text-center sm:px-6">
                                <h3 class="text-sm font-semibold text-slate-900">No on-page terms found</h3>
                                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">The scan did not find enough visible text on this site's public pages.</p>
                            </div>
                        @endif

                        <div class="border-t border-slate-100 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6">
                            Scanned {{ $audit['pages_scanned'] }} page(s) on {{ $audit['website'] }}. Google links are manual checks only; result order varies by location and personalization, and this scan does not measure rankings.
                        </div>
                    </section>

                    @if($audit['pages'] !== [])
                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="pages-heading">
                            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                                <h2 id="pages-heading" class="text-lg font-semibold text-slate-950">Page-by-page SEO details</h2>
                                <p class="mt-1 text-sm text-slate-500">Title, meta description, and headings each scanned page uses</p>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                @foreach($audit['pages'] as $page)
                                    <li class="px-5 py-4 sm:px-6">
                                        <details class="group">
                                            <summary class="flex cursor-pointer list-none items-start justify-between gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-slate-950">{{ $page['title'] !== '' ? $page['title'] : 'Missing page title' }}</span>
                                                    <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $page['url'] }}</span>
                                                </span>
                                                <span class="shrink-0 text-xs text-slate-500">
                                                    {{ number_format($page['word_count']) }} words
                                                    <span class="ml-2 font-semibold text-indigo-700 group-open:hidden">Details +</span>
                                                    <span class="ml-2 hidden font-semibold text-indigo-700 group-open:inline">Hide −</span>
                                                </span>
                                            </summary>
                                            <dl class="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                                <div>
                                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Meta description</dt>
                                                    @if($page['description'] !== '')
                                                        <dd class="mt-1 leading-6 text-slate-800">{{ $page['description'] }}</dd>
                                                    @else
                                                        <dd class="mt-1 font-medium text-rose-700">Missing meta description</dd>
                                                    @endif
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Headings</dt>
                                                    @if($page['headings'] !== [])
                                                        <dd class="mt-1">
                                                            <ul class="list-disc space-y-0.5 pl-5 text-slate-800">
                                                                @foreach($page['headings'] as $heading)
                                                                    <li>{{ $heading }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </dd>
                                                    @else
                                                        <dd class="mt-1 text-slate-600">No headings found</dd>
                                                    @endif
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Top terms on this page</dt>
                                                    <dd class="mt-1 flex flex-wrap gap-1.5">
                                                        @forelse($page['keywords'] as $pageKeyword)
                                                            <span class="rounded-md bg-white px-2 py-1 text-xs font-medium text-slate-700">{{ $pageKeyword['term'] }} · {{ number_format($pageKeyword['occurrences']) }}</span>
                                                        @empty
                                                            <span class="text-slate-600">No terms found</span>
                                                        @endforelse
                                                    </dd>
                                                </div>
                                                <div>
                                                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-indigo-700 hover:underline">Open page</a>
                                                </div>
                                            </dl>
                                        </details>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @elseif(!$searchAttempted)
                    <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 text-indigo-700">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path stroke-linecap="round" d="m20 20-4-4"></path>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-base font-semibold text-slate-950">Start with a competitor website</h2>
                        <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">Scan public pages to see their on-page terms and where those terms appear.</p>
                        <a href="{{ route('search-console.index') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">See your own Google rankings <span class="ml-1" aria-hidden="true">&rarr;</span></a>
                    </section>
                @endif
            </main>
        </div>
    </div>
</body>
</html>
