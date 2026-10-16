<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\DTOs\Auth\LoginDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function login(Request $request, LoginAction $action): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string',
        ]);

        $dto = new LoginDTO(
            email: $request->email,
            password: $request->password,
            deviceName: $request->device_name
        );

        $result = $action->execute($dto);

        return response()->json([
            'message' => 'Login exitoso',
            'data' => $result,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_active && ! $request->user()->deleted_at, 401, 'Cuenta inactiva.');

        return response()->json($request->user()->load('roles')->setAttribute('permissions', $request->user()->getAllPermissions()->pluck('name')));
    }

    public function logout(Request $request, LogoutAction $action): Response
    {
        $action->execute($request->user());

        return response()->noContent();
    }
}
