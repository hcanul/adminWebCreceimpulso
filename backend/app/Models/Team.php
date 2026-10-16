<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['branch_id', 'promoter_id', 'name', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function promoter()
    {
        return $this->belongsTo(Promoter::class);
    }

    public function memberships()
    {
        return $this->hasMany(TeamMembership::class);
    }
}
