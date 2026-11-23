<?php

namespace App\Domains\Onboarding\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterOnboardingLoginAction
{
    public const TOUR = 'admin-web';

    public function execute(User $user, int $tokenId): void
    {
        DB::transaction(function () use ($user, $tokenId) {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $current->admin_onboarding_enabled) return;
            $count = DB::table('user_onboarding_visits')->where('user_id', $user->id)->where('tour', self::TOUR)->count();
            if ($count >= 2) return;
            DB::table('user_onboarding_visits')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $user->id, 'tour' => self::TOUR,
                'login_number' => $count + 1, 'token_id' => $tokenId, 'created_at' => now(),
            ]);
        });
    }
}
