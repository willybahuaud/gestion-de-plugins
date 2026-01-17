<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        return self::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    public function getModelLabelAttribute(): string
    {
        if (!$this->model_type) {
            return '-';
        }

        $shortType = class_basename($this->model_type);

        if ($this->model) {
            $identifier = match ($shortType) {
                'Product' => $this->model->name,
                'User' => $this->model->email,
                'Admin' => $this->model->email,
                'License' => $this->model->uuid,
                'Release' => "v{$this->model->version}",
                'Price' => $this->model->name,
                'ApiToken' => $this->model->name,
                'WebhookEndpoint' => $this->model->name,
                default => "#{$this->model_id}",
            };
            return "{$shortType}: {$identifier}";
        }

        return "{$shortType} #{$this->model_id}";
    }
}
