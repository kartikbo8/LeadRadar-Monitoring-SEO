{{-- Full health report for one completed run (results['report'] version 2). --}}
@php
    $report = $run->results['report'];
    $summary = $report['summary'];
    $links = $report['links'];
    $devices = ['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'];
    $responsiveByPage = collect($report['responsive'])->groupBy('url');
    [$problemPages, $okPages] = $responsiveByPage->partition(fn ($rows) => $rows->contains(fn ($row) => $row['status'] !== 'ok'));
    $pathOf = fn (string $url): string => (parse_url($url, PHP_URL_PATH) ?: '/').(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
    $errorCount = count($report['errors']) + count($report['broken_resources']);
    $securityTone = $summary['security_failures'] > 0
        ? 'border-rose-200 bg-rose-50'
        : ($summary['security_problems'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50');
    $badge = [
        'ok' => ['Fits screen', 'bg-emerald-100 text-emerald-800'],
        'overflow' => ['Overflows', 'bg-amber-100 text-amber-900'],
        'failed' => ['Failed', 'bg-rose-100 text-rose-800'],
    ];
    $checkStyle = [
        'pass' => ['✓', 'bg-emerald-100 text-emerald-700', 'Pass'],
        'warn' => ['!', 'bg-amber-100 text-amber-800', 'Improve'],
        'fail' => ['✕', 'bg-rose-100 text-rose-700', 'Problem'],
        'info' => ['i', 'bg-sky-100 text-sky-700', 'Review'],
    ];
@endphp

<div class="mt-4 grid gap-5">
    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="#responsive-{{ $run->id }}" class="rounded-xl border p-4 transition hover:shadow-sm {{ $problemPages->isEmpty() ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
            <p class="text-xs font-medium text-slate-600">Responsive</p>
            <p class="mt-1 text-2xl font-semibold text-slate-950">{{ $okPages->count() }}<span class="text-base font-medium text-slate-500">/{{ $responsiveByPage->count() }}</span></p>
            <p class="mt-0.5 text-xs text-slate-600">pages fit every screen size</p>
        </a>
        <a href="#links-{{ $run->id }}" class="rounded-xl border p-4 transition hover:shadow-sm {{ $summary['broken_links'] === 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
            <p class="text-xs font-medium text-slate-600">Broken links</p>
            <p class="mt-1 text-2xl font-semibold text-slate-950">{{ $summary['broken_links'] }}</p>
            <p class="mt-0.5 text-xs text-slate-600">of {{ $summary['links_checked'] }} links checked</p>
        </a>
        <a href="#errors-{{ $run->id }}" class="rounded-xl border p-4 transition hover:shadow-sm {{ $errorCount === 0 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
            <p class="text-xs font-medium text-slate-600">Page errors</p>
            <p class="mt-1 text-2xl font-semibold text-slate-950">{{ $errorCount }}</p>
            <p class="mt-0.5 text-xs text-slate-600">script &amp; file errors</p>
        </a>
        <a href="#security-{{ $run->id }}" class="rounded-xl border p-4 transition hover:shadow-sm {{ $securityTone }}">
            <p class="text-xs font-medium text-slate-600">Security &amp; hacking</p>
            <p class="mt-1 text-2xl font-semibold text-slate-950">{{ $summary['security_problems'] === 0 ? 'Clean' : $summary['security_problems'] }}</p>
            <p class="mt-0.5 text-xs text-slate-600">{{ $summary['security_problems'] === 0 ? 'no warning signs found' : 'checks need attention' }}</p>
        </a>
    </div>

    {{-- Responsive --}}
    <section id="responsive-{{ $run->id }}" class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h5 class="text-sm font-semibold text-slate-950">Responsive status</h5>
            <p class="text-xs font-medium text-slate-600">{{ $responsiveByPage->count() }} {{ $responsiveByPage->count() === 1 ? 'page' : 'pages' }} tested · <span class="{{ $problemPages->isEmpty() ? 'text-emerald-700' : 'text-amber-800' }}">{{ $problemPages->count() }} with problems</span></p>
        </div>
        <p class="mt-1 text-xs text-slate-500">Every crawled page was opened in a real browser at mobile (390px), tablet (768px) and desktop (1440px) widths.</p>

        {{-- Elements that overflow on many pages are usually a shared header/footer component: fix once, fixes everywhere. --}}
        @php
            $sharedCauses = collect($report['responsive'])
                ->where('status', 'overflow')
                ->flatMap(fn ($row) => collect($row['elements'])->map(fn ($element) => ['element' => $element, 'url' => $row['url'], 'device' => $row['device']]))
                ->groupBy('element')
                ->map(fn ($hits) => ['pages' => $hits->pluck('url')->unique()->count(), 'devices' => $hits->pluck('device')->unique()->values()->all()])
                ->filter(fn ($cause) => $cause['pages'] >= 3)
                ->sortByDesc('pages');
        @endphp
        @if($sharedCauses->isNotEmpty())
            <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
                <p class="text-xs font-semibold text-amber-950">Site-wide cause: fix these once to fix every page</p>
                <ul class="mt-2 grid gap-1.5">
                    @foreach($sharedCauses as $element => $cause)
                        <li class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                            <code class="break-all rounded bg-white px-1.5 py-0.5 font-mono text-[11px] text-slate-800">{{ $element }}</code>
                            <span class="text-amber-900">goes off-screen on {{ $cause['pages'] }} {{ $cause['pages'] === 1 ? 'page' : 'pages' }} ({{ implode(', ', $cause['devices']) }})</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-5 text-amber-900">These look like shared components (header, footer, forms). In Webflow, open the symbol/component once, switch to the tablet and mobile views, and remove fixed widths or extra padding so it fits within the screen.</p>
            </div>
        @endif
        <div class="mt-3 grid gap-3">
            @foreach($problemPages as $url => $rows)
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-indigo-700 hover:underline">{{ $pathOf($url) }}</a>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach($devices as $deviceKey => $deviceLabel)
                            @php($row = $rows->firstWhere('device', $deviceKey))
                            @continue($row === null)
                            <div class="rounded-lg bg-white p-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-slate-700">{{ $deviceLabel }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badge[$row['status']][1] }}">{{ $badge[$row['status']][0] }}</span>
                                </div>
                                @if($row['status'] !== 'ok')
                                    <p class="mt-1.5 text-xs leading-5 text-slate-600">{{ $row['detail'] }}</p>
                                    @if($row['elements'] !== [])
                                        <p class="mt-1 text-[11px] font-medium text-slate-500">Elements going off-screen:</p>
                                        <ul class="mt-0.5 grid gap-0.5">
                                            @foreach($row['elements'] as $element)
                                                <li class="break-all font-mono text-[11px] text-slate-700">{{ $element }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($okPages->isNotEmpty())
            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                <p class="text-xs font-semibold text-emerald-900">{{ $okPages->count() }} {{ $okPages->count() === 1 ? 'page fits' : 'pages fit' }} correctly on mobile, tablet and desktop</p>
                <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                    @foreach($okPages as $url => $rows)
                        <li class="flex min-w-0 items-center gap-2 text-xs">
                            <span class="shrink-0 font-bold text-emerald-600" aria-hidden="true">✓</span>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="truncate text-slate-700 hover:text-indigo-700 hover:underline" title="{{ $url }}">{{ $pathOf($url) }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- Links --}}
    <section id="links-{{ $run->id }}" class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <h5 class="text-sm font-semibold text-slate-950">Links</h5>
        <p class="mt-1 text-xs text-slate-500">
            {{ $links['checked'] }} links checked{{ isset($links['found']) && $links['truncated'] ? ' (out of '.$links['found'].' found; the first '.\App\Services\LinkHealthChecker::MAX_INTERNAL_LINKS.' internal and '.\App\Services\LinkHealthChecker::MAX_EXTERNAL_LINKS.' external links are checked)' : '' }}.
        </p>

        @if($links['broken'] === [])
            <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">No broken links found. All {{ $links['ok'] }} working links responded correctly.</p>
        @else
            <ul class="mt-3 grid gap-2">
                @foreach($links['broken'] as $link)
                    <li class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-rose-900 underline">{{ $link['url'] }}</a>
                            <span class="w-fit shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-rose-800">{{ $link['status'] ? 'HTTP '.$link['status'] : 'No response' }} · {{ $link['internal'] ? 'internal' : 'external' }}</span>
                        </div>
                        <p class="mt-1 text-xs text-rose-900">{{ $link['reason'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">Found on <a href="{{ $link['found_on'] }}" target="_blank" rel="noopener noreferrer" class="break-all underline">{{ $pathOf($link['found_on']) }}</a></p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs leading-5 text-slate-600"><span class="font-semibold">Fix:</span> {{ \App\Support\HealthIssueAdvice::fix('broken_link') }}</p>
        @endif

        @if($links['unverified'] !== [])
            <details class="mt-3">
                <summary class="cursor-pointer text-xs font-semibold text-slate-600">{{ count($links['unverified']) }} link(s) could not be verified automatically</summary>
                <ul class="mt-2 grid gap-1">
                    @foreach($links['unverified'] as $link)
                        <li class="text-xs text-slate-600"><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="break-all underline">{{ $link['url'] }}</a> — {{ $link['reason'] }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if($links['redirects'] !== [])
            <details class="mt-2">
                <summary class="cursor-pointer text-xs font-semibold text-slate-600">{{ count($links['redirects']) }} link(s) redirect elsewhere (working, but could point straight to the final page)</summary>
                <ul class="mt-2 grid gap-1">
                    @foreach($links['redirects'] as $link)
                        <li class="break-all text-xs text-slate-600">{{ $link['url'] }} → {{ $link['location'] ?: '(no location)' }} <span class="text-slate-400">(HTTP {{ $link['status'] }})</span></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    {{-- Errors --}}
    <section id="errors-{{ $run->id }}" class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <h5 class="text-sm font-semibold text-slate-950">Page errors</h5>
        <p class="mt-1 text-xs text-slate-500">JavaScript errors and images, CSS or scripts that failed to load while the pages were open in the browser.</p>

        @if($errorCount === 0)
            <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">No script errors or failed files were found on the tested pages.</p>
        @else
            <ul class="mt-3 grid gap-2">
                @foreach($report['errors'] as $error)
                    <li class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="break-words font-mono text-xs text-amber-950">{{ $error['message'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">On {{ collect($error['pages'])->map($pathOf)->implode(', ') }} · {{ implode(', ', $error['devices']) }}</p>
                    </li>
                @endforeach
                @foreach($report['broken_resources'] as $resource)
                    <li class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm font-medium text-amber-950">{{ ucfirst($resource['type']) }} failed to load ({{ $resource['status'] ? 'HTTP '.$resource['status'] : $resource['error'] }})</p>
                        <p class="mt-0.5 break-all font-mono text-xs text-amber-900">{{ $resource['url'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">On {{ collect($resource['pages'])->map($pathOf)->implode(', ') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Security --}}
    <section id="security-{{ $run->id }}" class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <h5 class="text-sm font-semibold text-slate-950">Security &amp; hacking check</h5>
        <p class="mt-1 text-xs text-slate-500">Common warning signs of a hacked or poorly protected site.</p>
        <ul class="mt-3 divide-y divide-slate-100">
            @foreach($report['security'] as $check)
                @php([$icon, $iconClass, $statusLabel] = $checkStyle[$check['status']])
                <li class="flex gap-3 py-2.5">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $iconClass }}" aria-hidden="true">{{ $icon }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">{{ $check['label'] }} <span class="sr-only">— {{ $statusLabel }}</span></p>
                        <p class="break-words text-xs leading-5 text-slate-600">{{ $check['detail'] }}</p>
                        @if($check['fix'])
                            <p class="mt-1 text-xs leading-5 text-slate-700"><span class="font-semibold">What to do:</span> {{ $check['fix'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Precautions --}}
    @if($report['precautions'] !== [])
        <section class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 sm:p-5">
            <h5 class="text-sm font-semibold text-slate-950">What to fix</h5>
            <ol class="mt-3 grid gap-3">
                @foreach($report['precautions'] as $precaution)
                    <li class="rounded-lg bg-white p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase {{ $precaution['severity'] === 'error' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900' }}">{{ $precaution['severity'] === 'error' ? 'High' : 'Medium' }}</span>
                            <p class="text-sm font-semibold text-slate-900">{{ $precaution['title'] }}</p>
                            <span class="text-xs text-slate-500">{{ $precaution['count'] }} {{ $precaution['count'] === 1 ? 'place' : 'places' }}</span>
                        </div>
                        <p class="mt-1.5 text-xs leading-5 text-slate-700">{{ $precaution['fix'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <p class="text-xs leading-5 text-slate-500">
        Covers {{ $run->results['pages_scanned'] ?? 0 }} crawled pages (up to {{ \App\Services\WebsiteAuditService::MAX_HEALTH_CHECK_PAGES }} per check), each rendered in a browser at three screen sizes. These are warning-sign checks, not a full malware scan; a clean result does not guarantee the site is uncompromised.
    </p>
</div>
