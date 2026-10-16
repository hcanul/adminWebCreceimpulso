<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Actions\DisburseCreditAction;
use App\Domains\Credit\DTOs\DisburseCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\DisburseCreditRequest;
use Illuminate\Http\Request;

final class CreditDisbursementController extends Controller
{
    public function show(Request $request, CreditAccess $access, CreditDisbursement $service, string $id)
    {
        return response()->json($service->payload($request->user(), $access->query($request->user())->findOrFail($id)));
    }

    public function store(DisburseCreditRequest $request, DisburseCreditAction $action, CreditDisbursement $service, string $id)
    {
        $loan = $action->execute($request->user(), $id, DisburseCreditData::fromArray($request->validated()));

        return response()->json($service->payload($request->user(), $loan));
    }
}
