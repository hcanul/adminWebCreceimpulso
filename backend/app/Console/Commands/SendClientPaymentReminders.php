<?php
namespace App\Console\Commands;
use App\Domains\Push\Services\PushSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class SendClientPaymentReminders extends Command {
    protected $signature = 'clients:payment-reminders';
    protected $description = 'Envía recordatorios privados de cuotas pendientes que vencen mañana.';
    public function handle(PushSender $sender): int {
        if (!config('client_push.enabled')) { $this->info('Push deshabilitado: no se realizan envíos.'); return self::SUCCESS; }
        $tomorrow = now(config('client_push.timezone'))->addDay()->toDateString();
        $devices = DB::table('client_push_devices')->where('enabled', true)->orderBy('id');
        $accepted = 0; $other = 0;
        $devices->chunkById(100, function ($batch) use ($sender, $tomorrow, &$accepted, &$other) {
            foreach ($batch as $device) {
                if (!$sender->ready($device->platform) || !$this->eligible($device, $tomorrow)) continue;
                $id = (string) Str::uuid();
                // Claim durably BEFORE network IO. Unknown outcomes are never resent automatically.
                if (!DB::table('client_payment_reminders')->insertOrIgnore(['id'=>$id,'client_account_id'=>$device->client_account_id,'installation_id'=>$device->installation_id,'due_on'=>$tomorrow,'status'=>'sending','created_at'=>now(),'updated_at'=>now()])) continue;
                try {
                    if (!$this->eligible($device, $tomorrow)) {
                        $result = ['status'=>'cancelled','reference'=>null];
                    } else {
                        $result = $sender->send($device->platform, Crypt::decryptString($device->token_encrypted), substr(hash('sha256', $device->client_account_id.$tomorrow),0,48));
                    }
                } catch (\Throwable $e) {
                    // Never log provider request, token, credentials or exception body.
                    $result = ['status'=>'unknown','reference'=>null];
                }
                DB::table('client_payment_reminders')->where('id',$id)->update(['status'=>$result['status'],'provider_reference'=>$result['reference'],'updated_at'=>now()]);
                if ($result['status'] === 'invalid_device') DB::table('client_push_devices')->where('id',$device->id)->update(['enabled'=>false,'updated_at'=>now()]);
                if ($result['status'] === 'accepted') $accepted++; else $other++;
            }
        });
        $this->info("Aceptados por proveedor: {$accepted}; requieren revisión/cancelados: {$other}.");
        return self::SUCCESS;
    }
    private function eligible(object $device, string $date): bool {
        $password = DB::table('client_accounts')->where('id', $device->client_account_id)->value('password');
        if (!is_string($password) || !hash_equals($device->credential_fingerprint, hash('sha256', $password))) return false;
        return DB::table('client_push_devices as d')
            ->join('client_accounts as a','a.id','=','d.client_account_id')
            ->join('clients as c','c.id','=','a.client_id')
            ->join('branches as b','b.id','=','c.branch_id')
            ->where('d.id',$device->id)->where('d.enabled',true)
            ->where('d.client_account_id',$device->client_account_id)->where('d.token_hash',$device->token_hash)
            ->where('a.is_active',true)->where('a.must_change_password',false)
            ->where('b.is_active',true)->whereNull('c.deleted_at')
            ->whereIn('c.status',['activo','moroso'])
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('loan_installments as i')
                ->join('loan_applications as l','l.id','=','i.loan_application_id')
                ->whereColumn('i.client_id','c.id')->where('i.due_on',$date)
                ->whereColumn('i.amount_paid','<','i.amount_due')->where('l.status','entregado')->whereNull('l.deleted_at'))
            ->exists();
    }
}
