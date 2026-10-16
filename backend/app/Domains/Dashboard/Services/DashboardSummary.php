<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator as Money;
use App\Domains\Intake\Services\IntakeAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DashboardSummary
{
    public function forUser(User $actor): array
    {
        abort_unless($actor->is_active && ! $actor->deleted_at, 403);

        return DB::transaction(function () use ($actor) {
            $now = now('America/Cancun');
            $today = $now->toDateString();
            $can = fn (string $p) => $actor->hasRole('Super Admin') || $actor->can($p);
            $intake = app(IntakeAccess::class);
            $global = $intake->global($actor);
            $groups = $intake->allowed($actor) ? $intake->teams($actor)->where('is_active', true)->count() : null;
            $review = $active = null;
            $inbox = [];
            $add = function (string $label, int $count, string $to) use (&$inbox) {
                if ($count > 0) {
                    $inbox[] = ['label' => $label, 'count' => $count, 'to' => $to];
                }
            };
            if ($can('credit.view')) {
                $rows = app(CreditAccess::class)->query($actor)->select(['status', 'review_status', 'authorization_status'])->selectRaw('COUNT(*) as total')->groupBy('status', 'review_status', 'authorization_status')->get();
                $pending = $rows->where('status', 'pendiente');
                $review = (int) $pending->whereIn('review_status', ['pending', 'in_review'])->sum('total');
                $active = (int) $rows->where('status', 'entregado')->sum('total');
                if ($can('credit.review')) {
                    $add('Solicitudes pendientes de revisión', (int) $pending->where('review_status', 'pending')->sum('total'), '/gestion?status=pending');
                    $add('Revisiones en curso', (int) $pending->where('review_status', 'in_review')->sum('total'), '/gestion?status=in_review');
                }
                if ($can('credit.create')) {
                    $add('Solicitudes con observaciones', (int) $pending->where('review_status', 'returned')->sum('total'), '/solicitudes-credito?status=returned');
                }
                $ready = $pending->where('review_status', 'ready_for_authorization');
                if ($can('credit.propose')) {
                    $add('Condiciones por proponer', (int) $ready->where('authorization_status', 'awaiting_proposal')->sum('total'), '/autorizaciones?status=awaiting_proposal');
                    $add('Propuestas devueltas', (int) $ready->where('authorization_status', 'returned')->sum('total'), '/autorizaciones?status=returned');
                }
                if ($can('credit.authorize')) {
                    $add('Créditos por autorizar', (int) $ready->where('authorization_status', 'pending_authorization')->sum('total'), '/autorizaciones?status=pending_authorization');
                }
                if ($can('credit.disburse')) {
                    $add('Créditos por entregar', (int) $rows->where('status', 'aprobado')->sum('total'), '/desembolsos?status=aprobado');
                }
            }
            $payments = null;
            if ($can('credit.collect') || $can('cash.audit')) {
                $q = DB::table('credit_payments')->where('received_on', $today)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'credit_payments.id'));
                if (! $global) {
                    $q->where('branch_id', $actor->branch_id ?? '');
                }
                $allCashiers = $can('cash.audit');
                if (! $allCashiers) {
                    $q->where('actor_id', $actor->id);
                }
                $totals = $q->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN method = 'cash_branch' THEN amount ELSE 0 END), 0) as cash, COALESCE(SUM(CASE WHEN method = 'bank_deposit' THEN amount ELSE 0 END), 0) as bank")->first();
                $cash = Money::scaled((string) $totals->cash);
                $bank = Money::scaled((string) $totals->bank);
                $payments = ['count' => (int) $totals->total, 'total' => Money::money($cash + $bank), 'cash' => Money::money($cash), 'bank' => Money::money($bank), 'scope' => $allCashiers ? ($global ? 'Todas las sucursales' : 'Tu sucursal') : 'Tus cobros'];
            }
            if ($can('cash.view') && $can('cash.close')) {
                $uncut = DB::table('credit_payments as p')->where('p.actor_id', $actor->id)->where('p.received_on', $today)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('credit_payment_reversals as r')->whereColumn('r.credit_payment_id', 'p.id'))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('cash_closing_payments as c')->whereColumn('c.credit_payment_id', 'p.id'));
                if (! $global) {
                    $uncut->where('p.branch_id', $actor->branch_id ?? '');
                }
                $add('Tus cobros de hoy pendientes de corte', $uncut->count(), '/cortes');
            }

            return ['as_of' => $now->toIso8601String(), 'business_date' => $today, 'scope' => $global ? 'Todas las sucursales' : (DB::table('branches')->where('id', $actor->branch_id)->value('name') ?? 'Sin sucursal asignada'), 'groups' => $groups, 'in_review' => $review, 'active_credits' => $active, 'payments' => $payments, 'inbox' => $inbox];
        });
    }
}
