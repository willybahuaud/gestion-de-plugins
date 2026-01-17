<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'license_key',
        'user_id',
        'product_id',
        'price_id',
        'status',
        'expires_at',
        'max_activations',
        'stripe_subscription_id',
        'grace_period_days',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_activations' => 'integer',
            'grace_period_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (License $license) {
            if (empty($license->license_key)) {
                $license->license_key = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isLifetime(): bool
    {
        return $this->expires_at === null;
    }

    public function canActivate(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->max_activations === 0) {
            return true; // Illimité
        }

        return $this->activations()->count() < $this->max_activations;
    }

    public function getActivationsCountAttribute(): int
    {
        return $this->activations()->count();
    }

    public function getRemainingActivationsAttribute(): int
    {
        if ($this->max_activations === 0) {
            return -1; // Illimité
        }

        return max(0, $this->max_activations - $this->activations_count);
    }
}
