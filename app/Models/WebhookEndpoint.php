<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'product_ids',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'product_ids' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function shouldReceiveEvent(string $event, ?int $productId = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Vérifier si l'événement est dans la liste
        if (!in_array($event, $this->events ?? [])) {
            return false;
        }

        // Vérifier le filtre par produit
        if ($productId && !empty($this->product_ids)) {
            if (!in_array($productId, $this->product_ids)) {
                return false;
            }
        }

        return true;
    }

    public function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
