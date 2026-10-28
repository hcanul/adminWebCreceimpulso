<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class ClientDemoController extends Controller
{
    public function show(Request $request)
    {
        $client = $request->user()->client;
        if (! app()->environment('local', 'testing') || $client->folio !== 'CLI-S009-000001' || $client->branch->name !== 'DEMO APP — Solo pruebas' || ! str_starts_with($client->curp, 'DEMO')) {
            return response()->json(['demo' => false])->header('Cache-Control', 'no-store');
        }
        $start = CarbonImmutable::parse('2026-09-27');
        $asOf = CarbonImmutable::parse('2026-10-25');
        $schedule = [];
        for ($i = 1; $i <= 16; $i++) {
            $date = $start->addWeeks($i - 1);
            $paid = $i <= 2 ? 80000 : ($i === 3 ? 50000 : 0);
            $schedule[] = ['week' => $i, 'due_on' => $date->toDateString(), 'amount' => 80000, 'paid' => $paid, 'remaining' => 80000 - $paid, 'status' => $paid === 80000 ? 'Pagado' : ($date->lt($asOf) ? ($paid ? 'Parcial · vencido' : 'Vencido') : ($date->eq($asOf) ? 'Vence hoy' : 'Próximo'))];
        }
        $receipts = [];
        foreach ([['DEMO-001', '2026-09-27', 80000, 'Efectivo en sucursal', null], ['DEMO-002', '2026-10-04', 80000, 'Depósito bancario', 'BANCO-DEMO-204'], ['DEMO-003', '2026-10-11', 50000, 'Efectivo en sucursal', null]] as [$id,$date,$amount,$method,$reference]) {
            $receipts[] = ['id' => $id, 'date' => $date, 'amount' => $amount, 'method' => $method, 'reference' => $reference, 'verification_url' => URL::signedRoute('client.demo.receipt', ['receipt' => $id])];
        }

        return response()->json(['demo' => true, 'as_of' => '2026-10-25', 'loan' => ['folio' => 'CRE-DEMO-000001', 'group' => 'Mujeres del Amanecer · DEMO', 'role' => 'Integrante', 'principal' => 1000000, 'interest' => 280000, 'total' => 1280000, 'paid' => 210000, 'balance' => 1070000, 'guarantee' => 100000, 'delivered' => 900000, 'weekly' => 80000, 'overdue' => 110000, 'due_today' => 80000, 'late_fees' => 14000, 'payable' => 204000, 'next_due' => '2026-11-01', 'first_due' => '2026-09-27', 'last_due' => '2027-01-10', 'days_overdue' => 14, 'rate' => 'Cobre · $80 por cada $1,000 por semana'], 'schedule' => $schedule, 'receipts' => array_reverse($receipts), 'fees' => [['date' => '2026-10-12', 'week' => 3, 'base' => 30000, 'amount' => 3000], ['date' => '2026-10-19', 'week' => 3, 'base' => 30000, 'amount' => 3000], ['date' => '2026-10-19', 'week' => 4, 'base' => 80000, 'amount' => 8000]]])->header('Cache-Control', 'no-store');
    }

    public function receipt(string $receipt)
    {
        abort_unless(app()->environment('local', 'testing') && in_array($receipt, ['DEMO-001', 'DEMO-002', 'DEMO-003'], true), 404);

        return response('<!doctype html><html lang="es"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Comprobante DEMO</title><body style="font:18px system-ui;padding:32px;background:#f5f5f8;color:#292b35"><h1>Crece Impulso</h1><h2>Ejemplo verificado: '.e($receipt).'</h2><p>Firma del enlace válida.</p><strong>DEMOSTRACIÓN — SIN VALIDEZ DE PAGO</strong><p>Este QR pertenece a un ejemplo ficticio. No acredita dinero recibido ni corresponde a una operación real.</p></body></html>')->header('Cache-Control', 'no-store');
    }
}
