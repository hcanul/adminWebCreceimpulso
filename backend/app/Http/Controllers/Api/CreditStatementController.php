<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditStatement;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class CreditStatementController extends Controller
{
    public function __invoke(Request $request, CreditStatement $statement, string $id)
    {
        return response()->json($statement->payload($request->user(), $id))
            ->header('Cache-Control', 'private, no-store');
    }
}
