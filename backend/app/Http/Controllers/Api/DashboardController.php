<?php

namespace App\Http\Controllers\Api;

use App\Domains\Dashboard\Services\DashboardSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary)
    {
        return response()->json($summary->forUser($request->user()))->header('Cache-Control', 'no-store, private');
    }
}
