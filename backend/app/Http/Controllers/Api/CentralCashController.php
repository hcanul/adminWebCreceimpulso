<?php

namespace App\Http\Controllers\Api;

use App\Domains\Cash\Services\CentralCashReport;
use App\Domains\Credit\Services\CreditCalculator;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class CentralCashController extends Controller
{
    private function filters(Request $r): array
    {
        $v = $r->validate(['search' => 'nullable|string|max:100', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'branch_id' => 'nullable|uuid', 'method' => 'sometimes|in:all,cash_branch,bank_deposit', 'page' => 'sometimes|integer|min:1|max:1000000']);
        abort_if(CarbonImmutable::parse($v['from'])->diffInDays(CarbonImmutable::parse($v['to'])) > 366, 422, 'Consulta periodos de hasta un año.');

        return $v;
    }

    public function show(Request $r, CentralCashReport $service)
    {
        return response()->json($service->payload($r->user(), $this->filters($r)))->header('Cache-Control', 'private, no-store');
    }

    public function options(Request $r, CentralCashReport $service)
    {
        return response()->json(['branches' => $service->branches($r->user()), 'today' => now('America/Cancun')->toDateString()]);
    }

    public function export(Request $r, CentralCashReport $service)
    {
        $filters = $this->filters($r);
        // Validate access before starting the streamed response.
        $query = $service->movements($r->user(), $filters);

        return response()->streamDownload(function () use ($service, $query, $filters) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $line = fn ($row) => fputcsv($output, $row, ',', '"', '', "\r\n");
            $text = fn ($s) => preg_match('/^[\s\p{Z}\p{C}]*[=+@\-]/u', $s ?? '') ? "'".$s : ($s ?? '');
            $line(['Caja central', $filters['from'], $filters['to'], 'MXN. Los reversos son ajustes, no devoluciones automáticas.']);
            $line(['Fecha operativa', 'Tipo', 'Sucursal', 'Crédito', 'Grupo', 'Número de pago', 'Medio', 'Importe', 'Cajero del cobro', 'Banco', 'Folio bancario', 'Fecha depósito', 'Corte original']);
            foreach ($service->details($query)->cursor() as $row) {
                $line([$row->event_on, $row->kind, $text($row->branch), $text($row->credit_folio), $text($row->group), $row->number, $row->method, ($row->kind === 'reversal' ? '-' : '').CreditCalculator::money(CreditCalculator::scaled((string) $row->amount)), $text($row->cashier), $text($row->bank_name), $text($row->bank_reference), $row->deposited_on, $text($row->closing_folio)]);
            }
            fclose($output);
        }, 'caja-central-'.$filters['from'].'-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
