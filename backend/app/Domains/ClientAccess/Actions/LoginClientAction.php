<?php

namespace App\Domains\ClientAccess\Actions;

use App\Models\ClientAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginClientAction
{
    public function execute(string $identifier, string $password): array
    {
        $account = ClientAccount::whereHas('client', fn ($q) => $q->where('folio', mb_strtoupper(trim($identifier))))->with('client.branch')->first();
        // Always verify a hash to avoid a fast response for unknown identifiers.
        $valid = Hash::check($password, $account?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $valid || ! $account || ! $account->is_active || ! in_array($account->client->status, ['activo', 'moroso'], true) || ! $account->client->branch?->is_active) {
            throw ValidationException::withMessages(['identifier' => 'No fue posible iniciar sesión. Revisa tu número de cliente y contraseña.']);
        }
        $expires = now()->addHours(8);
        $token = $account->createToken('client-app', ['client:read'], $expires)->plainTextToken;

        return ['token' => $token, 'expires_at' => $expires->toIso8601String()];
    }
}
