<?php

namespace App\Http\Controllers\Api;

use App\Domains\Push\Services\PushSender;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StaffPushController extends Controller
{
    public function show(Request $request, PushSender $sender)
    {
        $data = $request->validate(['installation_id' => ['required', 'uuid']]);

        return response()->json([
            'available' => ['android' => $sender->ready('android'), 'ios' => $sender->ready('ios')],
            'enabled' => DB::table('staff_push_devices')->where('user_id', $request->user()->id)->where('installation_id', $data['installation_id'])->where('credential_fingerprint', hash('sha256', $request->user()->password))->where('enabled', true)->exists(),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, PushSender $sender)
    {
        $data = $request->validate(['platform' => ['required', 'in:android,ios'], 'token' => ['required', 'string', 'min:32', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/'], 'installation_id' => ['required', 'uuid'], 'consent' => ['required', 'accepted']]);
        abort_unless($sender->ready($data['platform']), 503, 'Los recordatorios aún no están habilitados por la sucursal.');
        if ($data['platform'] === 'ios') {
            abort_unless(ctype_xdigit($data['token']), 422, 'Token de dispositivo inválido.');
        }
        $hash = hash('sha256', $data['token']);
        DB::transaction(function () use ($request, $data, $hash) {
            DB::table('staff_push_devices')->where('user_id', $request->user()->id)->where('installation_id', $data['installation_id'])->update(['enabled' => false, 'updated_at' => now()]);
            DB::table('staff_push_devices')->upsert([
                'id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
                'session_token_id' => $request->user()->currentAccessToken()->id, 'credential_fingerprint' => hash('sha256', $request->user()->password), 'installation_id' => $data['installation_id'],
                'platform' => $data['platform'], 'token_hash' => $hash, 'token_encrypted' => Crypt::encryptString($data['token']),
                'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
            ], ['token_hash'], ['user_id', 'session_token_id', 'credential_fingerprint', 'installation_id', 'platform', 'token_encrypted', 'enabled', 'updated_at']);
        });

        return response()->json(['enabled' => true])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['installation_id' => ['required', 'uuid']]);
        DB::table('staff_push_devices')->where('user_id', $request->user()->id)->where('installation_id', $data['installation_id'])->update(['enabled' => false, 'updated_at' => now()]);

        return response()->noContent();
    }
}
