<?php

namespace App\Models\Concerns;

use App\Domains\Identity\Services\FolioSequence;

trait HasBranchFolio
{
    protected static function bootHasBranchFolio(): void
    {
        static::creating(function ($model) {
            // Only the server assigns folios; client-supplied values are never trusted.
            $model->folio = app(FolioSequence::class)->issue($model->branch_id, $model->folioKind());
        });
        static::updating(function ($model) {
            if ($model->isDirty('folio') || $model->isDirty('branch_id')) {
                throw new \LogicException('El folio y la sucursal de origen son inmutables.');
            }
        });
    }

    abstract protected function folioKind(): string;
}
