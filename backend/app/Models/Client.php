<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use \App\Models\Concerns\HasBranchFolio, HasUuids, SoftDeletes;

    protected function folioKind(): string
    {
        return 'client';
    }

    protected $fillable = ['branch_id', 'curp', 'first_name', 'last_name', 'phone', 'address', 'status', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
