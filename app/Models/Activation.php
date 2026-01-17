<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'domain',
        'is_active',
        'ip_address',
        'local_ip',
        'activated_at',
        'last_check_at',
        'deactivated_at',
        'plugin_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'last_check_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Normalise un domaine (supprime www, protocole, trailing slash)
     */
    public static function normalizeDomain(?string $domain): string
    {
        if ($domain === null) {
            return '';
        }
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');

        return $domain;
    }

    public function setDomainAttribute(string $value): void
    {
        $this->attributes['domain'] = self::normalizeDomain($value);
    }
}
