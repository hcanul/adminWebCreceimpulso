<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rate extends Model
{
    use HasUuids, SoftDeletes;

    protected $appends = ['terms_hash'];

    public function getTermsHashAttribute(): string
    {
        return hash('sha256', json_encode($this->only(['id', 'name', 'min_amount', 'max_amount', 'rate_factor', 'is_active', 'calculation_method']), JSON_THROW_ON_ERROR));
    }

    protected $fillable = ['name', 'cicle', 'min_amount', 'max_amount', 'rate_factor', 'is_active', 'calculation_method'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2', 'max_amount' => 'decimal:2', 'rate_factor' => 'decimal:4', 'is_active' => 'boolean', 'cicle' => 'integer'];
    }
}
