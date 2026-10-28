<?php

namespace App\Http\Controllers\Api;

use App\Domains\ClientAccess\Actions\LoginClientAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class ClientSessionController extends Controller
{
    public function login(Request $request, LoginClientAction $action)
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:128']]);

        return response()->json($action->execute($data['identifier'], $data['password']))->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        $client = $request->user()->client;

        return response()->json(['name' => $client->first_name.' '.$client->last_name, 'folio' => $client->folio, 'branch' => $client->branch->name])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
