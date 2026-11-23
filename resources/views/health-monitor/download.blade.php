<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Health report: {{ $site->host }}</title>
    <style>{!! $styles !!}</style>
    <style>
        @media print {
            .no-print { display: none !important; }
            details { display: block; }
            details > :not(summary) { display: block !important; }
            section { break-inside: avoid-page; }
        }
    </style>
</head>
<body class="bg-white text-slate-900">
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-bold tracking-wide text-indigo-700">LeadRadar</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Website health report</h1>
            <p class="mt-1 break-all text-sm text-slate-600">{{ $site->website_url }}</p>
            <p class="mt-1 text-xs text-slate-500">Checked {{ ($run->scanned_at ?? $run->created_at)->format('M j, Y g:i A') }}</p>
        </div>
        <button type="button" onclick="window.print()" class="no-print w-fit rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Print / Save as PDF</button>
    </header>

    @include('health-monitor._report', ['run' => $run])
</main>
</body>
</html>
