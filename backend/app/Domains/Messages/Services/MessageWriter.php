<?php

namespace App\Domains\Messages\Services;

use App\Domains\Credit\Services\CreditCalculator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MessageWriter
{
    public function create(string $type, string $recipient, ?string $branch, string $event, string $kind, string $title, string $body, ?string $installment = null, ?string $payment = null, ?string $sender = null, $expires = null): string
    {
        $key = hash('sha256', $type.':'.$recipient.':'.$event);
        $id = (string) Str::uuid();
        DB::table('app_messages')->insertOrIgnore(['id' => $id, 'recipient_type' => $type, 'recipient_id' => $recipient, 'branch_id' => $branch, 'sender_id' => $sender, 'event_key' => $key, 'kind' => $kind, 'title' => $title, 'body' => Crypt::encryptString($body), 'installment_id' => $installment, 'payment_id' => $payment, 'expires_at' => $expires ?? now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('app_messages')->where('event_key', $key)->value('id');
    }

    public function payment(string $id): void
    {
        $p = DB::table('credit_payments')->where('id', $id)->first();
        $beneficiaries = DB::table('credit_payment_allocations as a')->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')->where('a.credit_payment_id', $id)->pluck('i.client_id');
        $payers = DB::table('credit_payment_allocations')->where('credit_payment_id', $id)->pluck('payer_client_id');
        $fee = DB::table('late_fee_payment_allocations as a')->join('weekly_late_fees as f', 'f.id', '=', 'a.weekly_late_fee_id')->where('a.credit_payment_id', $id)->get(['f.client_id', 'a.payer_client_id']);
        $ids = $beneficiaries->merge($payers)->merge($fee->pluck('client_id'))->merge($fee->pluck('payer_client_id'))->unique();
        foreach (DB::table('client_accounts')->whereIn('client_id', $ids)->where('is_active', true)->get() as $a) {
            $this->create('client', $a->id, $p->branch_id, 'payment:'.$id, 'payment_received', 'Pago registrado', 'Registramos un pago en el que aportaste o recibiste una aplicación. Consulta tu comprobante y saldo actualizado en la app.', null, $id);
        }
    }

    public function stillRelevant(object $m): bool
    {
        if ($m->payment_id) {
            return ! DB::table('credit_payment_reversals')->where('credit_payment_id', $m->payment_id)->exists();
        }
        if (! $m->installment_id) {
            return true;
        }
        $i = DB::table('loan_installments as i')->join('loan_applications as l', 'l.id', '=', 'i.loan_application_id')->where('i.id', $m->installment_id)->whereNull('l.deleted_at')->whereIn('l.status', ['entregado', 'pagado'])->first(['i.*']);
        if (! $i) {
            return false;
        }$paid = CreditCalculator::scaled((string) $i->amount_paid) >= CreditCalculator::scaled((string) $i->amount_due);

        return $m->kind === 'after_thanks' ? $paid : ! $paid;
    }
}
