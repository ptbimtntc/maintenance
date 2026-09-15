<?php

namespace App\Concerns;

use App\Models\AuditLog;

/**
 * Writes an append-only audit trail entry on create/update/delete for any
 * model that uses this trait. Applied to the models the project brief calls
 * out specifically: employees, certificates, skill assessments, job
 * descriptions (which covers approvals - they are just status updates),
 * training records, and development plans.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->auditableChanges();

            if (! empty($changes)) {
                $model->writeAuditLog('updated', $changes);
            }
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', null);
        });
    }

    /**
     * The changed attributes, excluding timestamps (which change on every
     * save and would otherwise flood the log with content-free entries).
     */
    protected function auditableChanges(): array
    {
        return collect($this->getChanges())
            ->except(['created_at', 'updated_at'])
            ->all();
    }

    protected function writeAuditLog(string $action, ?array $changes): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }
}
