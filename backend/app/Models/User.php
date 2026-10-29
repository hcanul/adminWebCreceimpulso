<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasUuids, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'previous_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $appends = ['password_change_required', 'password_expires_at'];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password')) {
                if ($user->exists) $user->previous_password = $user->getRawOriginal('password');
                $user->password_changed_at = now();
            }
        });
    }

    public function getPasswordChangeRequiredAttribute(): bool
    {
        return ! $this->password_changed_at || now()->greaterThanOrEqualTo($this->password_changed_at->copy()->addDays(15));
    }

    public function getPasswordExpiresAtAttribute(): ?string
    {
        return $this->password_changed_at?->copy()->addDays(15)->toIso8601String();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
        ];
    }
}
