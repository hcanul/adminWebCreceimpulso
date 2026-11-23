<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChangeStaffPasswordAction
{
    public function execute(User $actor, string $current, string $password): array
    {
        return DB::transaction(function () use ($actor, $current, $password) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->is_active && ! $user->deleted_at, 403);
            if (! Hash::check($current, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
            }
            if (Hash::check($password, $user->password) || ($user->previous_password && Hash::check($password, $user->previous_password))) {
                throw ValidationException::withMessages(['password' => 'Usa una contraseña diferente de la actual y de la anterior.']);
            }
            $user->password = $password;
            $user->save();
            // Token renewal belongs to the same login and must not consume a tour.
            $currentToken = $actor->currentAccessToken();
            $visitId = $currentToken instanceof \Laravel\Sanctum\PersonalAccessToken
                ? DB::table('user_onboarding_visits')->where('user_id', $user->id)->where('token_id', $currentToken->id)->value('id')
                : null;
            $user->tokens()->delete();
            DB::table('access_audits')->insert([
                'id' => (string) Str::uuid(), 'actor_id' => $user->id, 'subject_type' => 'user',
                'subject_id' => $user->id, 'action' => 'password_changed',
                'changes' => json_encode(['password_changed' => true]), 'created_at' => now(),
            ]);
            $token = $user->createToken('Sesión renovada', ['*'], now()->addHours(8));

            if ($visitId) DB::table('user_onboarding_visits')->where('id', $visitId)->update(['token_id' => $token->accessToken->id]);

            // Return the renewed profile with the token: a second network request must
            // not make a successful password change appear to have failed.
            $profile = $user->load('roles')->toArray();
            $profile['permissions'] = $user->getAllPermissions()->pluck('name')->unique()->values()->all();

            return ['token' => $token->plainTextToken, 'user' => $profile, 'roles' => $user->getRoleNames()];
        });
    }
}
