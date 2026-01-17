<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_invoice_id',
        'user_id',
        'license_id',
        'number',
        'amount_total',
        'amount_tax',
        'currency',
        'status',
        'stripe_pdf_url',
        'local_pdf_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_total' => 'integer',
            'amount_tax' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_total / 100, 2, ',', ' ') . ' €';
    }

    public function getFormattedTaxAttribute(): string
    {
        return number_format($this->amount_tax / 100, 2, ',', ' ') . ' €';
    }

    public function getAmountWithoutTaxAttribute(): int
    {
        return $this->amount_total - $this->amount_tax;
    }
}
