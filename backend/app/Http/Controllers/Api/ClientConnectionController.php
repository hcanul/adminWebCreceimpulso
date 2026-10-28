<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class ClientConnectionController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['challenge' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/']]);

        return response()->json(['service' => 'creceimpulso-client-api', 'challenge' => $data['challenge'], 'checked_at' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }
}
