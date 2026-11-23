
@vite(['resources/css/app.css', 'resources/js/app.js'])

<style>
    body {
        margin: 0;
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: #f1eff6;
    }
</style>

<div class="min-h-screen bg-[#f1eff6] px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <header class="mb-8">
            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700">
                LeadRadar Site Audit
            </span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Domain overview</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                Scan a website for on-page keywords and page details.
            </p>
            <a href="{{ route('competitors.index') }}" class="mt-3 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">
                Scan a competitor's page keywords <span class="ml-1" aria-hidden="true">&rarr;</span>
            </a>
            <a href="{{ route('health-monitor.index') }}" class="mt-3 ml-4 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">
                Monitor an authorized website <span class="ml-1" aria-hidden="true">&rarr;</span>
            </a>
        </header>

        <form method="GET" action="{{ route('companies.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <label for="website" class="mb-2 block text-sm font-semibold text-slate-800">Website domain</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    id="website"
                    type="text"
                    name="website"
                    value="{{ old('website', $website) }}"
                    placeholder="https://example.com"
                    autocomplete="url"
                    required
                    class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                >
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200"
                >
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path stroke-linecap="round" d="m20 20-4-4"></path>
                    </svg>
                    Analyze website
                </button>
            </div>
            @error('website')
                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
            @enderror
            <p class="mt-3 text-xs leading-5 text-slate-500">
                Scans up to 10 same-domain public pages. No SEO API key is needed.
            </p>
        </form>

        @if($auditError)
            <div role="alert" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                {{ $auditError }}
            </div>
        @endif

        @if($audit)
            <section class="mt-8 space-y-6" aria-labelledby="overview-heading">
                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Analyzed domain</p>
                        <h2 id="overview-heading" class="mt-1 text-2xl font-semibold text-slate-950">{{ $audit['domain'] }}</h2>
                    </div>
                    <a href="{{ $audit['website'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">
                        Open website <span aria-hidden="true">&nearr;</span>
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Pages analyzed</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $audit['pages_scanned'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Maximum 10 same-domain pages</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">On-page keywords</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">{{ count($audit['keywords']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Unique terms found in page text</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Missing descriptions</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $audit['missing_descriptions'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Pages without a meta description</p>
                    </article>
                </div>

                @if($audit['pages_skipped'] > 0)
                    <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ $audit['pages_skipped'] }} internal {{ $audit['pages_skipped'] === 1 ? 'page was' : 'pages were' }} unreachable and skipped.
                    </div>
                @endif

                <div role="note" class="flex flex-col gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm leading-6 text-indigo-950 sm:flex-row sm:items-center sm:justify-between">
                    <p>This scan shows which keywords appear on each page. It does not show Google rankings. For your own verified website, use Search Console.</p>
                    <a href="{{ route('search-console.index') }}" class="shrink-0 font-semibold text-indigo-700 hover:text-indigo-900 hover:underline">Open Search Console &rarr;</a>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1.9fr)]">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="keywords-heading">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 id="keywords-heading" class="text-lg font-semibold text-slate-950">Top on-page keywords</h2>
                                <p class="mt-1 text-sm text-slate-500">Click a keyword to see the pages containing it</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Up to 20</span>
                        </div>

                        <div class="mt-5 divide-y divide-slate-100">
                            @forelse($audit['keywords'] as $keyword)
                                <details class="group py-3 first:pt-0 last:pb-0">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-2 py-1 text-sm transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                        <span class="min-w-0 truncate font-medium text-slate-800">{{ $keyword['term'] }}</span>
                                        <span class="flex shrink-0 items-center gap-2 text-xs text-slate-500">
                                            <span>{{ $keyword['pages'] }} {{ $keyword['pages'] === 1 ? 'page' : 'pages' }}</span>
                                            <span class="rounded-lg bg-indigo-50 px-2 py-1 text-center font-semibold text-indigo-700">{{ number_format($keyword['occurrences']) }} {{ $keyword['occurrences'] === 1 ? 'occurrence' : 'occurrences' }}</span>
                                        </span>
                                    </summary>
                                    <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Found on these pages</p>
                                        <ul class="mt-2 grid gap-2">
                                            @foreach($keyword['page_details'] as $page)
                                                <li class="flex min-w-0 items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <a href="#scanned-page-{{ $page['index'] }}" class="block truncate text-sm font-medium text-indigo-700 hover:underline">
                                                            {{ $page['title'] !== '' ? $page['title'] : $page['url'] }}
                                                        </a>
                                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $page['url'] }}</span>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-2">
                                                        <span class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-slate-700">{{ number_format($page['occurrences']) }} {{ $page['occurrences'] === 1 ? 'occurrence' : 'occurrences' }}</span>
                                                        <a href="{{ route('search-console.index', ['query' => $keyword['term'], 'page' => $page['url']]) }}" class="text-right text-xs font-semibold text-indigo-700 hover:underline">View ranking graph</a>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </details>
                            @empty
                                <p class="py-4 text-sm text-slate-500">No meaningful text keywords were found.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="pages-heading">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <h2 id="pages-heading" class="text-lg font-semibold text-slate-950">Scanned pages</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ number_format($audit['total_words']) }} words analyzed</p>
                            </div>
                            <span class="text-xs font-medium text-slate-500">On-page SEO</span>
                        </div>

                        @foreach($audit['pages'] as $page)
                            <article id="scanned-page-{{ $loop->index }}" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer" class="break-all text-xs text-indigo-700 hover:underline">
                                            {{ $page['url'] }}
                                        </a>
                                        <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $page['title'] }}</h3>
                                    </div>
                                    <span class="w-fit shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                        {{ number_format($page['word_count']) }} words
                                    </span>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-slate-600">
                                    {{ $page['description'] !== '' ? $page['description'] : 'No meta description found.' }}
                                </p>

                                @if($page['headings'] !== [])
                                    <div class="mt-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Headings</p>
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach($page['headings'] as $heading)
                                                <li class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700">{{ $heading }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if($page['keywords'] !== [])
                                    <div class="mt-5 border-t border-slate-100 pt-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h4 class="text-sm font-semibold text-slate-900">Top 5 keywords found on this page</h4>
                                            <span class="text-xs text-slate-500">Click a keyword to view its details</span>
                                        </div>
                                        <ul class="mt-3 flex flex-wrap gap-2">
                                            @foreach($page['keywords'] as $keyword)
                                                <li>
                                                    <details class="group">
                                                        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-800 transition hover:border-indigo-300 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                            <span>{{ $keyword['term'] }} · {{ number_format($keyword['occurrences']) }} {{ $keyword['occurrences'] === 1 ? 'occurrence' : 'occurrences' }}</span>
                                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-600 group-open:hidden">Details +</span>
                                                            <span class="hidden text-[10px] font-semibold uppercase tracking-wide text-indigo-600 group-open:inline">Hide −</span>
                                                        </summary>
                                                        <div class="mt-2 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-sm">
                                                            <p>Found on this page {{ $keyword['occurrences'] }} times.</p>
                                                            <a href="{{ route('search-console.index', ['query' => $keyword['term'], 'page' => $page['url']]) }}" class="w-fit font-semibold text-indigo-700 hover:underline">View ranking graph in Search Console &rarr;</a>
                                                        </div>
                                                    </details>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </section>
                </div>

                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                    These are keywords counted from the website's visible page text. They are not Google rankings, search volume, traffic estimates, or competitor data.
                </p>
            </section>
        @elseif(!$scanAttempted)
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center">
                <h2 class="text-lg font-semibold text-slate-900">Start with a domain</h2>
                <p class="mt-2 text-sm text-slate-600">Enter a public website above to see its on-page SEO overview.</p>
            </div>
        @endif
    </div>
</div>
