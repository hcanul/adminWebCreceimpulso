<?php

namespace App\Http\Controllers\Api;

use App\Domains\Documents\Services\OperationalReports;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class OperationalReportController extends Controller
{
    public function __invoke(Request $request, OperationalReports $reports, string $kind)
    {
        abort_unless(in_array($kind, ['portfolio', 'statement', 'deposits', 'collection_log', 'agenda'], true), 404);
        $filters = $request->validate(['portfolio' => 'nullable|in:ordinary,recovery,all', 'branch_id' => 'nullable|uuid', 'credit_id' => 'nullable|uuid', 'client_id' => 'nullable|uuid', 'week_on' => 'nullable|date_format:Y-m-d', 'search' => 'nullable|string|max:100', 'status' => 'nullable|in:all,overdue,today,current']);
        if (in_array($kind, ['statement', 'deposits', 'collection_log'], true)) {
            $request->validate(['portfolio' => 'nullable|in:ordinary,recovery,all', 'branch_id' => 'nullable|uuid', 'credit_id' => 'required|uuid']);
        }

        return response()->json(['html' => $reports->render($request->user(), $kind, $filters)])->header('Cache-Control', 'private, no-store');
    }
}
