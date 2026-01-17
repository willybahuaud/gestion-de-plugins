<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log('created', $model, null, $model->getAuditableAttributes());
        });

        static::updated(function ($model) {
            $old = $model->getOriginal();
            $new = $model->getAttributes();

            // Ne loguer que les champs modifiés
            $changes = array_diff_assoc(
                array_intersect_key($new, $old),
                array_intersect_key($old, $new)
            );

            if (!empty($changes)) {
                $changedKeys = array_keys($changes);
                AuditLog::log(
                    'updated',
                    $model,
                    array_intersect_key($old, array_flip($changedKeys)),
                    array_intersect_key($new, array_flip($changedKeys))
                );
            }
        });

        static::deleted(function ($model) {
            AuditLog::log('deleted', $model, $model->getAuditableAttributes(), null);
        });
    }

    public function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // Exclure les champs sensibles
        $hidden = array_merge($this->getHidden(), [
            'password',
            'secret',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);

        return array_diff_key($attributes, array_flip($hidden));
    }

    public function auditLogs()
    {
        return AuditLog::where('model_type', static::class)
            ->where('model_id', $this->id)
            ->orderByDesc('created_at');
    }
}
