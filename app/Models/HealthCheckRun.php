<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monitored_site_id', 'status', 'results', 'error', 'scanned_at', 'scheduled_for'])]
class HealthCheckRun extends Model
{
    protected function casts(): array
    {
        return [
            'results' => 'array',
            'scanned_at' => 'datetime',
            'scheduled_for' => 'date',
        ];
    }

    public function monitoredSite(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class);
    }
}
