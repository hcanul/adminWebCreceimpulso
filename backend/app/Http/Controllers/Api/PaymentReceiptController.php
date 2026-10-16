<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCollections;
use App\Domains\Tickets\Services\PaymentReceipt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PaymentReceiptController extends Controller
{
    public function show(Request $request, CreditAccess $access, PaymentReceipt $receipts, string $id)
    {
        CreditCollections::authorizeRead($request->user());
        $payment = DB::table('credit_payments')->where('id', $id)->first();
        abort_unless($payment, 404);
        $access->query($request->user())->findOrFail($payment->loan_application_id);

        return response()->json($receipts->payload($id, true))->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, PaymentReceipt $receipts, string $id)
    {
        $receipt = null;
        if ($request->hasValidSignature() && DB::table('credit_payments')->where('id', $id)->exists()) {
            $receipt = $receipts->payload($id);
        }

        return response()->view('receipts.verify', ['receipt' => $receipt], $receipt ? 200 : 404)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    }
}
