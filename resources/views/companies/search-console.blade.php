<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Google Rankings | LeadRadar</title>
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
                <a href="{{ route('search-console.index') }}" aria-current="page" class="block rounded-lg bg-indigo-50 px-3 py-2.5 font-semibold text-indigo-700">My Google rankings</a>
                <a href="{{ route('competitors.index') }}" class="block rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">Competitor page keywords</a>
                <a href="{{ route('companies.index') }}" class="block rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">Website audit</a>
            </nav>
            <div class="border-t border-slate-100 p-4">
                <p class="text-xs leading-5 text-slate-500">Google Search Console data is free and limited to properties your account can access.</p>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1440px] px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">SEO / Search performance</p>
                            <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950 sm:text-2xl">My Google rankings</h1>
                        </div>
                        @if($token !== null)
                            <form method="POST" action="{{ route('search-console.disconnect') }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Disconnect</button>
                            </form>
                        @endif
                    </div>
                    <nav aria-label="Mobile SEO tools" class="mt-4 flex gap-2 overflow-x-auto pb-1 text-sm lg:hidden">
                        <a href="{{ route('search-console.index') }}" aria-current="page" class="shrink-0 rounded-lg bg-indigo-50 px-3 py-2 font-semibold text-indigo-700">My Google rankings</a>
                        <a href="{{ route('competitors.index') }}" class="shrink-0 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Competitor page keywords</a>
                        <a href="{{ route('companies.index') }}" class="shrink-0 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Website audit</a>
                    </nav>
                </div>
            </header>

            <main class="mx-auto grid max-w-[1440px] gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                @if(session('status'))
                    <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif

                @if($searchError)
                    <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">{{ $searchError }}</div>
                @endif

                @if($selectedQuery !== '' && $selectedPage !== '')
                    <section class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 sm:px-5" aria-labelledby="selected-ranking-heading">
                        <h2 id="selected-ranking-heading" class="text-sm font-semibold text-indigo-950">Selected keyword and page</h2>
                        <p class="mt-1 break-words text-sm text-indigo-900"><span class="font-medium">{{ $selectedQuery }}</span></p>
                        <p class="mt-1 break-all text-xs leading-5 text-indigo-800">{{ $selectedPage }}</p>
                        @if($selectedSite === '')
                            <p class="mt-2 text-xs leading-5 text-indigo-800">Choose a Search Console property that includes this page to load its ranking graph.</p>
                        @endif
                    </section>
                @endif

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="connect-heading">
                    <div class="border-b border-slate-100 bg-gradient-to-br from-indigo-50 via-white to-white px-5 py-6 sm:px-7">
                        <span class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-white px-3 py-1 text-xs font-semibold text-indigo-700">
                            <span class="h-2 w-2 rounded-full {{ $token !== null ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                            {{ $token !== null ? 'Google account connected' : 'Free first-party data' }}
                        </span>
                        <h2 id="connect-heading" class="mt-4 text-lg font-semibold text-slate-950 sm:text-xl">Search Console performance</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            View the queries bringing people to your verified website, their Google average position, and the pages that appear in search.
                        </p>
                    </div>

                    @if(!$configured)
                        <div class="grid gap-5 p-5 sm:p-7 lg:grid-cols-[minmax(0,1.3fr)_minmax(260px,0.7fr)]">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Connect Google Search Console</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Set up a Google OAuth web client on your server to connect. No paid SEO API is used.</p>
                                <ol class="mt-4 grid gap-2 text-sm leading-6 text-slate-700">
                                    <li><span class="mr-2 font-semibold text-indigo-700">1.</span>Create an OAuth client in Google Cloud and enable the Search Console API.</li>
                                    <li><span class="mr-2 font-semibold text-indigo-700">2.</span>Add the callback URL below to the OAuth client's authorized redirect URIs.</li>
                                    <li><span class="mr-2 font-semibold text-indigo-700">3.</span>Set <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">GOOGLE_CLIENT_ID</code>, <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">GOOGLE_CLIENT_SECRET</code>, and <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">GOOGLE_REDIRECT_URI</code> in the server's <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">.env</code>.</li>
                                </ol>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">OAuth callback URL</p>
                                <code class="mt-2 block break-all text-xs leading-5 text-slate-800">{{ route('search-console.callback') }}</code>
                                <p class="mt-3 text-xs leading-5 text-slate-500">The URL must exactly match your app's public host and port. Never paste OAuth secrets into chat or source control.</p>
                            </div>
                        </div>
                    @elseif($token === null)
                        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Connect your Google account</h3>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Authorize read-only access to Search Console. Only properties available to that Google account will be listed.</p>
                            </div>
                            <a href="{{ route('search-console.connect', $rankingTarget) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">
                                Connect Search Console
                                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10"></path>
                                </svg>
                            </a>
                        </div>
                    @else
                        <div class="p-5 sm:p-7">
                            @if($sites === [])
                                <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                                    No Search Console properties were returned for this Google account. Add and verify your website in Search Console, then reconnect or refresh this page.
                                </div>
                            @else
                                <form method="GET" action="{{ route('search-console.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_auto]">
                                    @if($selectedQuery !== '' && $selectedPage !== '')
                                        <input type="hidden" name="query" value="{{ $selectedQuery }}">
                                        <input type="hidden" name="page" value="{{ $selectedPage }}">
                                    @endif
                                    <div class="min-w-0">
                                        <label for="site" class="mb-1.5 block text-sm font-medium text-slate-700">Search Console property</label>
                                        <select id="site" name="site" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                            <option value="">Choose a verified property</option>
                                            @foreach($sites as $site)
                                                <option value="{{ $site['site_url'] }}" @selected($selectedSite === $site['site_url'])>
                                                    {{ $site['site_url'] }} — {{ str_replace('_', ' ', $site['permission_level']) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('site')
                                            <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="range" class="mb-1.5 block text-sm font-medium text-slate-700">Date range</label>
                                        <select id="range" name="range" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                            <option value="7" @selected($range === '7')>Last 7 days</option>
                                            <option value="28" @selected($range === '28')>Last 28 days</option>
                                            <option value="90" @selected($range === '90')>Last 90 days</option>
                                        </select>
                                        @error('range')
                                            <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="self-end">
                                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200 sm:w-auto">
                                            Load report
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>

                @if($summary)
                    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Search performance summary">
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Google clicks</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['clicks']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Clicks from Google Search</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Impressions</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['impressions']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Times your site appeared</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Average CTR</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['ctr'] * 100, 1) }}%</p>
                            <p class="mt-1 text-xs text-slate-500">Clicks divided by impressions</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Average position</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['position'], 1) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Average for the selected period</p>
                        </article>
                    </section>

                    @if($selectedQuery !== '' && $selectedPage !== '')
                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="ranking-trend-heading">
                            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                <div class="min-w-0">
                                    <h2 id="ranking-trend-heading" class="text-lg font-semibold text-slate-950">Daily Google average position</h2>
                                    <p class="mt-1 break-words text-sm font-medium text-slate-700">{{ $selectedQuery }}</p>
                                    <p class="mt-1 break-all text-xs leading-5 text-slate-500">{{ $selectedPage }}</p>
                                </div>
                                <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700">{{ $selectedSite }}</span>
                            </div>

                            @if($trend !== [])
                                <div class="p-4 sm:p-6">
                                    <p class="mb-3 text-xs leading-5 text-slate-500">Each point is the average position for this exact query and page on a day with Search Console data. Lower numbers are better; this is not a fixed rank.</p>
                                    <div class="overflow-hidden">
                                        <svg class="h-auto w-full" viewBox="0 0 800 220" role="img" aria-labelledby="ranking-chart-title ranking-chart-description">
                                            <title id="ranking-chart-title">Daily average position for {{ $selectedQuery }}</title>
                                            <desc id="ranking-chart-description">Average Google position over time for {{ $selectedPage }}. Lower positions are better.</desc>
                                            <line x1="56" y1="25" x2="760" y2="25" stroke="#e2e8f0" stroke-dasharray="4 5"></line>
                                            <line x1="56" y1="90" x2="760" y2="90" stroke="#e2e8f0" stroke-dasharray="4 5"></line>
                                            <line x1="56" y1="155" x2="760" y2="155" stroke="#e2e8f0" stroke-dasharray="4 5"></line>
                                            <text x="48" y="29" text-anchor="end" fill="#64748b" font-size="11">{{ number_format($chartData['best_position'], 1) }}</text>
                                            <text x="48" y="159" text-anchor="end" fill="#64748b" font-size="11">{{ number_format($chartData['worst_position'], 1) }}</text>
                                            <polyline points="{{ $chartData['polyline'] }}" fill="none" stroke="#4f46e5" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"></polyline>
                                            @foreach($chartData['points'] as $point)
                                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#4f46e5" stroke="white" stroke-width="2">
                                                    <title>{{ $point['date'] }}: average position {{ number_format($point['position'], 1) }}, {{ number_format($point['clicks']) }} clicks, {{ number_format($point['impressions']) }} impressions</title>
                                                </circle>
                                                @if($point['date_label'] !== '')
                                                    <text x="{{ $point['x'] }}" y="184" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}" fill="#64748b" font-size="11">{{ $point['date_label'] }}</text>
                                                @endif
                                            @endforeach
                                        </svg>
                                    </div>
                                </div>
                            @else
                                <div class="px-5 py-10 text-center sm:px-6">
                                    <h3 class="text-sm font-semibold text-slate-900">No daily ranking data for this page and keyword</h3>
                                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Confirm the selected property includes this page and try a longer date range. Search Console may omit low-volume queries and can delay recent data.</p>
                                </div>
                            @endif
                        </section>
                    @endif

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="query-heading">
                        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <h2 id="query-heading" class="text-lg font-semibold text-slate-950">Queries and ranking pages</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $selectedSite }} · up to {{ number_format(count($rows)) }} query/page rows</p>
                            </div>
                            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700">
                                Average position is not a fixed rank
                            </span>
                        </div>

                        @if($rows !== [])
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[820px] text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th scope="col" class="px-5 py-3 sm:px-6">Search query</th>
                                            <th scope="col" class="px-5 py-3">Avg. position</th>
                                            <th scope="col" class="px-5 py-3">Clicks</th>
                                            <th scope="col" class="px-5 py-3">Impressions</th>
                                            <th scope="col" class="px-5 py-3">CTR</th>
                                            <th scope="col" class="px-5 py-3 sm:px-6">Page shown</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($rows as $row)
                                            <tr class="align-top transition hover:bg-slate-50/70">
                                                <td class="max-w-xs px-5 py-4 font-medium text-slate-900 sm:px-6">{{ $row['query'] }}</td>
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <span @class([
                                                        'inline-flex min-w-12 justify-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                                        'bg-emerald-50 text-emerald-700' => $row['position'] <= 10,
                                                        'bg-amber-50 text-amber-800' => $row['position'] > 10 && $row['position'] <= 30,
                                                        'bg-slate-100 text-slate-700' => $row['position'] > 30,
                                                    ])>{{ number_format($row['position'], 1) }}</span>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ number_format($row['clicks']) }}</td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ number_format($row['impressions']) }}</td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ number_format($row['ctr'] * 100, 1) }}%</td>
                                                <td class="max-w-sm px-5 py-4 text-xs leading-5 text-slate-600 sm:px-6"><span class="block truncate">{{ $row['page'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="px-5 py-12 text-center sm:px-6">
                                <h3 class="text-sm font-semibold text-slate-900">No query data for this period</h3>
                                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Try a longer date range. Search Console may also delay recent data by a few days.</p>
                            </div>
                        @endif

                        <div class="border-t border-slate-100 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6">
                            Google data can be delayed by a few days. This report is for the latest available dates within the selected range and is limited to 250 query/page combinations.
                        </div>
                    </section>
                @endif

                <div role="note" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-600">
                    Search Console can show clicks, impressions, queries, pages, and average position for properties your Google account can access. It cannot reveal competitor search rankings. <a href="{{ route('competitors.index') }}" class="font-semibold text-indigo-700 hover:text-indigo-900">Scan competitor page content instead</a>.
                </div>
            </main>
        </div>
    </div>
</body>
</html>
