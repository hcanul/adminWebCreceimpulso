<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class ClientAccount extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = ['client_id', 'password', 'is_active', 'must_change_password', 'temporary_password_expires_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'must_change_password' => 'boolean', 'temporary_password_expires_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
