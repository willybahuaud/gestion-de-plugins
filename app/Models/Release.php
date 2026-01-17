<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'version',
        'changelog',
        'file_path',
        'file_size',
        'file_hash',
        'min_php_version',
        'min_wp_version',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isPublished(): bool
    {
        return $this->is_published && $this->published_at && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->is_published && $this->published_at && $this->published_at->isFuture();
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Compare deux versions semver
     * Retourne: -1 si $this < $other, 0 si égal, 1 si $this > $other
     */
    public function compareVersion(string $other): int
    {
        return version_compare($this->version, $other);
    }
}
