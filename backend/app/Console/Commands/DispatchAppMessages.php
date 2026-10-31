<?php

namespace App\Console\Commands;

use App\Domains\Messages\Services\MessageAccess;
use App\Domains\Messages\Services\MessageWriter;
use App\Domains\Push\Services\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DispatchAppMessages extends Command
{
    protected $signature = 'messages:dispatch';

    protected $description = 'Envía avisos de bandeja pendientes a dispositivos autorizados.';

    public function handle(PushSender $sender, MessageAccess $access, MessageWriter $writer): int
    {
        if (! config('client_push.enabled')) {
            $this->info('Push deshabilitado. Los mensajes permanecen en la bandeja.');

            return self::SUCCESS;
        }
        $count = 0;
        DB::table('app_messages')->whereNull('cancelled_at')->where('expires_at', '>', now())->orderBy('id')->chunkById(100, function ($messages) use ($sender, $access, $writer, &$count) {
            foreach ($messages as $m) {
                if (! $access->active($m->recipient_type, $m->recipient_id)) {
                    continue;
                }
                if (! $writer->stillRelevant($m)) {
                    DB::table('app_messages')->where('id', $m->id)->update(['cancelled_at' => now()]);

                    continue;
                }
                $client = $m->recipient_type === 'client';
                $table = $client ? 'client_push_devices' : 'staff_push_devices';
                $column = $client ? 'client_account_id' : 'user_id';
                $password = DB::table($client ? 'client_accounts' : 'users')->where('id', $m->recipient_id)->value('password');
                foreach (DB::table($table)->where($column, $m->recipient_id)->where('enabled', true)->get() as $d) {
                    if (! $sender->ready($d->platform) || ! hash_equals($d->credential_fingerprint, hash('sha256', $password))) {
                        continue;
                    }
                    $id = (string) Str::uuid();
                    if (! DB::table('app_message_deliveries')->insertOrIgnore(['id' => $id, 'message_id' => $m->id, 'device_id' => $d->id, 'status' => 'sending', 'created_at' => now(), 'updated_at' => now()])) {
                        continue;
                    }
                    try {
                        $valid = $access->active($m->recipient_type, $m->recipient_id) && $writer->stillRelevant($m) && DB::table($table)->where('id', $d->id)->where($column, $m->recipient_id)->where('enabled', true)->where('token_hash', $d->token_hash)->exists();
                        $body = $m->kind === 'custom' ? 'Tu sucursal te envió un mensaje. Abre la app para consultarlo.' : Crypt::decryptString($m->body);
                        $ttl = (int) now()->diffInSeconds(CarbonImmutable::parse($m->expires_at), false);
                        $valid = $valid && $ttl > 0 && hash_equals($d->credential_fingerprint, hash('sha256', (string) DB::table($client ? 'client_accounts' : 'users')->where('id', $m->recipient_id)->value('password')));
                        $result = $valid ? $sender->sendMessage($d->platform, Crypt::decryptString($d->token_encrypted), $m->id, $m->kind === 'custom' ? 'Nuevo mensaje de Crece Impulso' : $m->title, $body, $m->recipient_type, min(43200, $ttl)) : ['status' => 'cancelled', 'reference' => null];
                    } catch (\Throwable) {
                        $result = ['status' => 'unknown', 'reference' => null];
                    }
                    DB::table('app_message_deliveries')->where('id', $id)->update(['status' => $result['status'], 'provider_reference' => $result['reference'], 'updated_at' => now()]);
                    if ($result['status'] === 'invalid_device') {
                        DB::table($table)->where('id', $d->id)->update(['enabled' => false, 'updated_at' => now()]);
                    }$count++;
                }
            }
        });
        $this->info("Intentos registrados: $count. accepted significa aceptación del proveedor, no lectura.");

        return self::SUCCESS;
    }
}
