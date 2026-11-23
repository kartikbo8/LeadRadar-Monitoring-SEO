<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'website_url', 'host', 'verification_token', 'verified_at', 'is_active', 'last_scanned_at'])]
#[Hidden(['verification_token'])]
class MonitoredSite extends Model
{
    protected function casts(): array
    {
        return [
            'verification_token' => 'encrypted',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    /**
     * Domains where the ownership TXT record may live: the monitored host itself,
     * then each parent domain down to the registrable domain (www.example.com → example.com).
     *
     * @return list<string>
     */
    public function verificationDomains(): array
    {
        $labels = explode('.', $this->host);
        $domains = [];

        while (count($labels) >= 2) {
            $domain = implode('.', $labels);

            // Stop at public suffixes such as co.in or com.au, which no site owner controls.
            if (count($labels) === 2 && strlen($labels[1]) === 2
                && in_array($labels[0], ['co', 'com', 'net', 'org', 'gov', 'ac', 'edu', 'gen', 'firm', 'ind', 'res'], true)) {
                break;
            }

            $domains[] = $domain;
            array_shift($labels);
        }

        return $domains === [] ? [$this->host] : $domains;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(HealthCheckRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(HealthCheckRun::class)->latestOfMany();
    }
}
