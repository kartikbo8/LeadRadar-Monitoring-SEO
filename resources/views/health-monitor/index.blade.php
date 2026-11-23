@vite(['resources/css/app.css', 'resources/js/app.js'])

<main class="min-h-screen bg-[#f1eff6] px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <header class="flex flex-col gap-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between sm:p-7">
            <div>
                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-indigo-700">LeadRadar Monitoring</span>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Website health monitor</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Monitor websites you own or are authorized to check. Verify domain control before any recurring scan is scheduled.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('companies.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Keyword audit</a>
                <form method="POST" action="{{ route('health-monitor.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Sign out</button>
                </form>
            </div>
        </header>

        @if(session('status'))
            <div role="status" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="grid gap-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="add-site-heading">
            <h2 id="add-site-heading" class="text-lg font-semibold text-slate-950">Add a website</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Enter a public domain, then add the DNS TXT record to prove you control it.</p>
            <form method="POST" action="{{ route('health-monitor.sites.store') }}" class="mt-4 grid gap-4">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row">
                    <label class="sr-only" for="website">Website domain</label>
                    <input id="website" type="text" name="website" value="{{ old('website') }}" placeholder="https://example.com" autocomplete="url" required class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">Add domain</button>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                    <input type="checkbox" name="authorization" value="1" required @checked(old('authorization')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>I own this website or have permission from its owner to run these passive and browser-rendered checks.</span>
                </label>
            </form>
        </section>

        <section class="mt-8 space-y-5" aria-labelledby="monitored-sites-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="monitored-sites-heading" class="text-xl font-semibold text-slate-950">Monitored websites</h2>
                    <p class="mt-1 text-sm text-slate-500">Daily checks run for verified sites with monitoring enabled.</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">{{ $sites->count() }} {{ $sites->count() === 1 ? 'site' : 'sites' }}</span>
            </div>

            @forelse($sites as $site)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="break-all text-lg font-semibold text-slate-950">{{ $site->host }}</h3>
                                @if($site->verified_at)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">DNS verified</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Verification required</span>
                                @endif
                            </div>
                            <a href="{{ $site->website_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block break-all text-sm text-indigo-700 hover:underline">{{ $site->website_url }}</a>
                            @if($site->last_scanned_at)
                                <p class="mt-2 text-xs text-slate-500">Last scan: {{ $site->last_scanned_at->format('M j, Y g:i A') }}</p>
                            @endif

                            @php($editingThisSite = (int) old('edit_site_id') === $site->id)
                            <details class="group mt-3" @if($editingThisSite && $errors->any()) open @endif>
                                <summary class="inline-flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-xl border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-100 [&::-webkit-details-marker]:hidden">
                                    <svg class="h-4 w-4 group-open:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z"/></svg>
                                    <span class="group-open:hidden">Edit website</span>
                                    <span class="hidden group-open:inline">Cancel edit</span>
                                </summary>
                                <form method="POST" action="{{ route('health-monitor.sites.update', $site->id) }}" class="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                                      onsubmit="const host = (u) => { try { return new URL(u.includes('://') ? u : 'https://' + u).hostname.toLowerCase().replace(/\.$/, ''); } catch { return ''; } }; return host(this.edit_website.value) === @js($site->host) || confirm('Changing the domain resets DNS verification, pauses daily checks, and deletes this site\'s check history. Continue?')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="edit_site_id" value="{{ $site->id }}">
                                    <label class="sr-only" for="edit-website-{{ $site->id }}">Website URL</label>
                                    <input id="edit-website-{{ $site->id }}" type="text" name="edit_website" value="{{ $editingThisSite ? old('edit_website') : $site->website_url }}" autocomplete="url" required class="min-w-0 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                    <p class="text-xs leading-5 text-slate-500">Checks always start from the site's homepage. Switching between http and https keeps verification; a different domain needs a new DNS TXT record.</p>
                                    <label class="flex items-start gap-3 text-sm text-slate-700">
                                        <input type="checkbox" name="authorization" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>I own this website or have permission from its owner to run these checks.</span>
                                    </label>
                                    <button type="submit" class="w-fit rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">Save changes</button>
                                </form>
                            </details>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if($site->verified_at)
                                <form method="POST" action="{{ route('health-monitor.sites.scan', $site->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">Run check now</button>
                                </form>
                                <form method="POST" action="{{ route('health-monitor.sites.toggle', $site->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $site->is_active ? '0' : '1' }}">
                                    <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        {{ $site->is_active ? 'Pause daily checks' : 'Enable daily checks' }}
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('health-monitor.sites.destroy', $site->id) }}" onsubmit="return confirm('Remove this website and its saved scan history?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Remove</button>
                            </form>
                        </div>
                    </div>

                    @if(!$site->verified_at)
                        @php($recordDomains = $site->verificationDomains())
                        @php($selectedRecordDomain = (int) old('record_site_id') === $site->id && in_array(old('record_domain'), $recordDomains, true) ? old('record_domain') : $site->host)
                        <form method="POST" action="{{ route('health-monitor.sites.verify', $site->id) }}" class="grid gap-4 bg-amber-50/60 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-6">
                            @csrf
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold text-amber-950">Add this DNS TXT record</h4>
                                <p class="mt-1 text-xs leading-5 text-amber-900">Create the record with your domain's DNS provider. Verification can take time while DNS changes propagate.</p>
                                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-[auto_minmax(0,1fr)]">
                                    <dt class="font-medium text-slate-600">Host / name</dt>
                                    <dd class="grid gap-1.5">
                                        @if(count($recordDomains) > 1)
                                            <fieldset class="grid gap-1.5">
                                                <legend class="sr-only">Where did you add the TXT record?</legend>
                                                @foreach($recordDomains as $recordDomain)
                                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-amber-200 bg-white px-3 py-2 has-[:checked]:border-amber-400 has-[:checked]:ring-2 has-[:checked]:ring-amber-100">
                                                        <input type="radio" name="record_domain" value="{{ $recordDomain }}" @checked($recordDomain === $selectedRecordDomain) class="h-4 w-4 border-amber-300 text-amber-600 focus:ring-amber-500">
                                                        <span class="min-w-0 break-all font-mono text-xs text-slate-800">_leadradar-verification.{{ $recordDomain }}</span>
                                                        <span class="ml-auto shrink-0 text-[11px] font-medium text-slate-500">{{ $recordDomain === $site->host ? 'this host' : 'root domain' }}</span>
                                                    </label>
                                                @endforeach
                                            </fieldset>
                                            <p class="text-xs leading-5 text-amber-900">Choose where you added the record. A root domain record also proves control of its subdomains.</p>
                                        @else
                                            <span class="break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-slate-800">_leadradar-verification.{{ $site->host }}</span>
                                        @endif
                                    </dd>
                                    <dt class="font-medium text-slate-600">TXT value</dt>
                                    <dd class="break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-slate-800">leadradar-verification={{ $site->verification_token }}</dd>
                                </dl>
                            </div>
                            <input type="hidden" name="method" value="dns">
                            <button type="submit" class="w-full rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 sm:w-auto">Verify DNS record</button>
                        </form>

                        <form method="POST" action="{{ route('health-monitor.sites.verify', $site->id) }}" class="grid gap-4 border-t border-amber-200/70 bg-amber-50/60 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-6">
                            @csrf
                            <input type="hidden" name="method" value="meta">
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold text-amber-950">No DNS access? Add a meta tag instead</h4>
                                <p class="mt-1 text-xs leading-5 text-amber-900">
                                    Paste this into the <code class="font-mono">&lt;head&gt;</code> of <span class="break-all font-medium">{{ $site->website_url }}</span>, publish the site, then verify.
                                    In Webflow: <span class="font-medium">Site settings → Custom code → Head code</span>, save, then <span class="font-medium">Publish</span>.
                                </p>
                                <div class="mt-3 break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-slate-800">&lt;meta name="leadradar-verification" content="{{ $site->verification_token }}"&gt;</div>
                            </div>
                            <button type="submit" class="w-full rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 sm:w-auto">Verify meta tag</button>
                        </form>
                    @else
                        <div class="border-b border-slate-100 px-5 py-3 sm:px-6">
                            <p class="text-xs font-medium {{ $site->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $site->is_active ? 'Daily monitoring is enabled.' : 'Daily monitoring is paused.' }}
                            </p>
                        </div>
                    @endif

                    <div class="p-5 sm:p-6">
                        <h4 class="text-sm font-semibold text-slate-900">Recent check history</h4>
                        @forelse($site->runs as $run)
                            @php($hasReport = $run->status === 'completed' && ($run->results['report']['version'] ?? 0) >= 2)
                            <details class="group/run mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4" @if($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-start sm:justify-between [&::-webkit-details-marker]:hidden" aria-label="Health check from {{ $run->created_at->format('M j, Y g:i A') }}">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-slate-400 transition group-open/run:rotate-90" aria-hidden="true">▸</span>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $run->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($run->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800') }}">{{ ucfirst($run->status) }}</span>
                                            @if($loop->first)
                                                <span class="text-xs font-semibold text-slate-700">Latest check</span>
                                            @endif
                                            @if($run->scheduled_for)
                                                <span class="text-xs text-slate-500">Scheduled for {{ $run->scheduled_for->format('M j, Y') }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 pl-5 text-xs text-slate-500">{{ $run->scanned_at?->format('M j, Y g:i A') ?? $run->created_at->format('M j, Y g:i A') }}</p>
                                    </div>
                                    @if($hasReport)
                                        @php($runSummary = $run->results['report']['summary'])
                                        <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                                            <span class="rounded-lg bg-white px-2.5 py-1.5">{{ $runSummary['pages_responsive'] ?? $runSummary['responsive_ok'] }}/{{ $runSummary['pages_tested'] ?? $runSummary['responsive_total'] }} {{ isset($runSummary['pages_tested']) ? 'pages' : 'screens' }} responsive</span>
                                            <span class="rounded-lg bg-white px-2.5 py-1.5">{{ $runSummary['broken_links'] }} broken {{ $runSummary['broken_links'] === 1 ? 'link' : 'links' }}</span>
                                            <span class="rounded-lg bg-white px-2.5 py-1.5">{{ $runSummary['security_problems'] === 0 ? 'Security clean' : $runSummary['security_problems'].' security '.($runSummary['security_problems'] === 1 ? 'warning' : 'warnings') }}</span>
                                        </div>
                                    @elseif($run->status === 'completed' && is_array($run->results))
                                        <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                                            <span class="rounded-lg bg-white px-2.5 py-1.5">{{ $run->results['pages_scanned'] ?? 0 }} {{ ($run->results['pages_scanned'] ?? 0) === 1 ? 'page' : 'pages' }} crawled</span>
                                            <span class="rounded-lg bg-white px-2.5 py-1.5">{{ count($run->results['issues'] ?? []) }} {{ count($run->results['issues'] ?? []) === 1 ? 'finding' : 'findings' }}</span>
                                        </div>
                                    @endif
                                </summary>

                                @if($run->status === 'queued')
                                    <p class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">Waiting to start. Checks run in the background queue, so a queue worker must be running (<code class="font-mono text-xs">php artisan queue:work</code>).</p>
                                @elseif($run->status === 'running')
                                    <p class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">Checking pages, links and security. This usually takes 1–3 minutes; refresh the page to see the report.</p>
                                @endif

                                @if($run->status === 'failed' && $run->error)
                                    <p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $run->error }}</p>
                                @endif

                                @if($hasReport)
                                    <div class="mt-4 flex justify-end">
                                        <a href="{{ route('health-monitor.sites.runs.download', ['siteId' => $site->id, 'runId' => $run->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 focus:outline-none focus:ring-4 focus:ring-indigo-100">
                                            <span aria-hidden="true">↓</span> Download report
                                        </a>
                                    </div>
                                    @include('health-monitor._report', ['run' => $run])
                                @elseif($run->status === 'completed' && is_array($run->results))
                                    <p class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs leading-5 text-slate-600">This is an older, basic report. It was made before the scanner loaded the site's CDN files, so JavaScript errors listed here may not be real. Run a new check for the full report.</p>
                                @if($run->status === 'completed' && is_array($run->results))
                                    @if(($run->results['issues'] ?? []) !== [])
                                        <ul class="mt-4 grid gap-2">
                                            @foreach($run->results['issues'] as $issue)
                                                <li class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                        <p class="text-sm font-medium text-amber-950">{{ $issue['message'] }}</p>
                                                        <span class="w-fit rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold uppercase text-amber-800">{{ $issue['severity'] }}{{ isset($issue['device']) ? ' · '.$issue['device'] : '' }}</span>
                                                    </div>
                                                    @if(!empty($issue['page']))
                                                        <a href="{{ $issue['page'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block break-all text-xs text-amber-800 underline">{{ $issue['page'] }}</a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">No findings were recorded by the checks run for this report.</p>
                                    @endif

                                    @if(($run->results['browser']['blocked_external_requests'] ?? 0) > 0)
                                        <p class="mt-3 text-xs leading-5 text-slate-500">{{ $run->results['browser']['blocked_external_requests'] }} cross-origin request(s) were blocked by the scanner's safety policy. External CDN assets and integrations were not tested.</p>
                                    @endif

                                    <p class="mt-3 text-xs leading-5 text-slate-500">
                                        Browser rendering covers up to three crawled pages at mobile, tablet, and desktop viewport sizes. Passive indicators can flag visible defacement wording and page or resource errors, but this is not a malware scan or a guarantee that every interaction is responsive or that the site is uncompromised.
                                    </p>
                                @endif
                                @endif
                            </details>
                        @empty
                            <p class="mt-2 text-sm leading-6 text-slate-500">No checks have run yet. Verify the domain to enable daily checks, or start a manual check after verification.</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center">
                    <h3 class="text-lg font-semibold text-slate-900">No websites added</h3>
                    <p class="mt-2 text-sm text-slate-600">Add a domain above to begin DNS ownership verification.</p>
                </div>
            @endforelse
        </section>
    </div>
</main>
