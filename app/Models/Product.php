<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'stripe_product_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function activePrices(): HasMany
    {
        return $this->hasMany(Price::class)->where('is_active', true);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function latestRelease()
    {
        return $this->hasOne(Release::class)
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
