<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Promoter extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'branch_id', 'is_active'];

    protected $with = ['user:id,name'];

    protected $appends = ['name'];

    protected $hidden = ['user'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')->whereColumn('users.branch_id', 'promoters.branch_id')->whereHas('roles', fn ($q) => $q->where('name', 'Promotor')->where('guard_name', 'web')));
    }
}
