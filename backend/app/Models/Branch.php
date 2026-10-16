<?php

namespace App\Models;

use App\Domains\Identity\Services\FolioSequence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            $branch->folio_code = app(FolioSequence::class)->branchCode();
        });
        static::updating(function (Branch $branch) {
            if ($branch->isDirty('folio_code')) {
                throw new \LogicException('El código de sucursal es inmutable.');
            }
        });
    }

    protected $fillable = ['name', 'city', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
