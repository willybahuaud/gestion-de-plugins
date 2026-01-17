<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'stripe_price_id',
        'name',
        'type',
        'amount',
        'currency',
        'interval',
        'max_activations',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'max_activations' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    public function isOneTime(): bool
    {
        return $this->type === 'one_time';
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2, ',', ' ') . ' €';
    }
}
