<?php

namespace App\Domains\Credit\Services;

final class PortfolioCsv
{
    /** @param resource $output */
    public function write($output, array $report, array $filters): void
    {
        // UTF-8 BOM preserves accents when the file is opened in Excel.
        fwrite($output, "\xEF\xBB\xBF");
        $line = fn (array $cells) => fputcsv($output, $cells, ',', '"', '', "\r\n");
        $text = fn (?string $value) => preg_match('/^[\s\p{Z}\p{C}]*[=+@\-]/u', $value ?? '') ? "'".$value : ($value ?? '');
        $line(['Cartera y vencimientos', 'Fecha operativa (Cancún)', $report['as_of']]);
        $line(['Filtro', match ($filters['status'] ?? 'all') {
            'overdue' => 'Con atraso (cuotas o mora)',
            'today' => 'Con cuota pendiente hoy',
            'current' => 'Sin atraso',
            default => 'Todos los activos',
        }, 'Búsqueda', $text($filters['search'] ?? '')]);
        $line(['Créditos', $report['total'], 'Con atraso', $report['summary']['overdue_credits'], 'Moneda', 'MXN']);
        $line(['La mora ya está incluida en saldo total, exigible y atraso. Vence hoy corresponde solo a cuotas.']);
        $line([]);
        $line(['UUID del crédito', 'Folio', 'Grupo', 'Sucursal', 'Cuotas pendientes', 'Mora pendiente', 'Saldo total', 'Exigible a la fecha', 'Vence hoy', 'En atraso', 'Días de atraso', 'Próxima cuota pendiente']);
        foreach ($report['data'] as $row) {
            $line([$row->id, $text($row->folio), $text($row->group), $text($row->branch), $row->installment_balance, $row->late_fee_balance, $row->balance, $row->due, $row->due_today, $row->overdue, $row->days_overdue, $row->next_due ?? '']);
        }
        $s = $report['summary'];
        $line(['TOTAL', '', '', '', $s['installment_balance'], $s['late_fee_balance'], $s['balance'], $s['due'], $s['due_today'], $s['overdue'], '', '']);
    }
}
