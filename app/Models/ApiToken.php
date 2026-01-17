<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Génère un nouveau token et retourne la version en clair (à afficher une seule fois)
     */
    public static function generateToken(): array
    {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        return [
            'plain' => $plainToken,
            'hashed' => $hashedToken,
        ];
    }

    /**
     * Trouve un token par sa valeur en clair
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        $hashedToken = hash('sha256', $plainToken);

        return self::where('token', $hashedToken)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function hasAbility(string $ability): bool
    {
        if (empty($this->abilities)) {
            return true; // Pas de restrictions = tout est permis
        }

        return in_array('*', $this->abilities) || in_array($ability, $this->abilities);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
