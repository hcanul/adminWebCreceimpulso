<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Actions\ReversePaymentAction;
use App\Domains\Credit\DTOs\ReversePaymentData;
use App\Domains\Tickets\Services\PaymentReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\ReversePaymentRequest;

final class PaymentReversalController extends Controller
{
    public function __invoke(ReversePaymentRequest $request, ReversePaymentAction $action, PaymentReceipt $receipts, string $id)
    {
        $v = $request->validated();
        $action->execute($request->user(), $id, new ReversePaymentData($v['id'], $v['balance_version'], trim($v['reason'])));

        return response()->json($receipts->payload($id, true))->header('Cache-Control', 'no-store, private');
    }
}
