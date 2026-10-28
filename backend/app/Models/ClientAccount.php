<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class ClientAccount extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = ['client_id', 'password', 'is_active'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
