<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditPortfolio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class CreditPortfolioController extends Controller
{
    public function __invoke(Request $request, CreditPortfolio $portfolio)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,overdue,today,current'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);

        return response()->json($portfolio->payload($request->user(), $filters))->header('Cache-Control', 'private, no-store');
    }
}
