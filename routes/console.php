<?php

use App\Jobs\RunMonitoredSiteHealthCheck;
use App\Models\MonitoredSite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static function (): void {
    MonitoredSite::query()
        ->whereNotNull('verified_at')
        ->where('is_active', true)
        ->chunkById(100, static function ($sites): void {
            foreach ($sites as $site) {
                $run = $site->runs()->firstOrCreate(
                    ['scheduled_for' => now()->toDateString()],
                    ['status' => 'queued'],
                );

                if ($run->wasRecentlyCreated) {
                    RunMonitoredSiteHealthCheck::dispatch($site->id, $run->id)->afterCommit();
                }
            }
        });
})->daily()->name('dispatch-daily-site-health-checks')->withoutOverlapping(120);
