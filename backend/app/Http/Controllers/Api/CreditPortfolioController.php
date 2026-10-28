<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditPortfolio;
use App\Domains\Credit\Services\PortfolioCsv;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class CreditPortfolioController extends Controller
{
    public function export(Request $request, CreditPortfolio $portfolio, PortfolioCsv $csv)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,overdue,today,current']]);
        $report = $portfolio->payload($request->user(), $filters, false);

        return response()->streamDownload(function () use ($csv, $report, $filters) {
            $output = fopen('php://output', 'w');
            $csv->write($output, $report, $filters);
            fclose($output);
        }, 'cartera-'.$report['as_of'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function __invoke(Request $request, CreditPortfolio $portfolio)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,overdue,today,current'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);

        return response()->json($portfolio->payload($request->user(), $filters))->header('Cache-Control', 'private, no-store');
    }
}
