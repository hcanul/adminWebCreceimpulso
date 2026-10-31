<?php

namespace App\Console\Commands;

use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Messages\Services\MessageWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PlanPaymentMessages extends Command
{
    protected $signature = 'messages:plan-payments';

    protected $description = 'Guarda avisos de mañana, hoy y seguimiento de ayer en la bandeja.';

    public function handle(MessageWriter $writer): int
    {
        $today = now('America/Cancun')->startOfDay();
        $count = 0;
        foreach ([-1, 0, 1] as $offset) {
            $date = $today->copy()->addDays($offset)->toDateString();
            DB::table('loan_installments as i')->join('loan_applications as l', 'l.id', '=', 'i.loan_application_id')->join('client_accounts as a', 'a.client_id', '=', 'i.client_id')->join('clients as c', 'c.id', '=', 'i.client_id')->join('branches as b', 'b.id', '=', 'c.branch_id')->where('i.due_on', $date)->whereIn('l.status', ['entregado', 'pagado'])->whereNull('l.deleted_at')->whereNull('c.deleted_at')->whereIn('c.status', ['activo', 'moroso'])->whereNull('b.deleted_at')->where('b.is_active', true)->where('a.is_active', true)->select(['i.id', 'i.branch_id', 'i.amount_due', 'i.amount_paid', 'a.id as account_id'])->orderBy('i.id')->chunk(200, function ($rows) use ($writer, $offset, $today, &$count) {
                foreach ($rows as $r) {
                    $paid = CreditCalculator::scaled((string) $r->amount_paid) >= CreditCalculator::scaled((string) $r->amount_due);
                    if ($offset >= 0 && $paid) {
                        continue;
                    }
                    [$kind,$title,$body] = match (true) {
                        $offset === 1 => ['before_due', 'Tu pago vence mañana', 'Recuerda que mañana vence tu pago semanal. Prepararlo a tiempo te ayuda a mantener tu crédito al corriente.'], $offset === 0 => ['due_today', 'Hoy es tu día de pago', 'Tu pago semanal vence hoy. Consulta tu saldo pendiente y realiza tu pago en sucursal o por el medio acordado.'], $paid => ['after_thanks', 'Gracias por tu pago', 'La cuota que venció ayer está cubierta. Gracias por mantener tu compromiso con el grupo. Consulta el detalle en tu estado de cuenta.'], default => ['after_reminder', 'Mantén tu crédito al corriente', 'La cuota que venció ayer aún tiene saldo pendiente. Pagar lo antes posible ayuda a reducir futuros cargos por atraso y a cuidar el historial del grupo. Si necesitas apoyo, comunícate con tu sucursal.']
                    };
                    $writer->create('client', $r->account_id, $r->branch_id, 'installment:'.$r->id.':'.$offset, $kind, $title, $body, $r->id, expires: $today->copy()->endOfDay()->utc());
                    $count++;
                }
            });
        }$this->info("Avisos revisados: $count. Los ya existentes no se duplican.");

        return self::SUCCESS;
    }
}
