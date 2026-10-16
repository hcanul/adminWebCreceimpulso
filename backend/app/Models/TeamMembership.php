<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeamMembership extends Model
{
    use HasUuids;

    protected $fillable = ['branch_id', 'team_id', 'client_id', 'role_in_group'];

    protected function casts(): array
    {
        return [];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
