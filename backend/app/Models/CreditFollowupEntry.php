<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreditFollowupEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Agrega una nueva observación para conservar el historial.'));
        static::deleting(fn () => throw new \LogicException('El historial de observaciones se conserva.'));
    }
}
