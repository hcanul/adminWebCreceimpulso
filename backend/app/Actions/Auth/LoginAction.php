<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function execute(LoginDTO $dto, ?string $app = null): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->is_active || $user->deleted_at) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta se encuentra inactiva.'],
            ]);
        }

        // Generar Token de Sanctum
        $token = $user->createToken($dto->deviceName, $user->password_change_required ? ['staff:password-change'] : ['*'], now()->addHours(8));

        if ($app === \App\Domains\Onboarding\Actions\RegisterOnboardingLoginAction::TOUR) {
            app(\App\Domains\Onboarding\Actions\RegisterOnboardingLoginAction::class)->execute($user, $token->accessToken->id);
        }

        return [
            'user' => (clone $user)->setAttribute('permissions', $user->getAllPermissions()->pluck('name')), // Clonamos para evitar mutar el original accidentalmente
            'token' => $token->plainTextToken,
            'roles' => $user->getRoleNames(),
        ];
    }
}
