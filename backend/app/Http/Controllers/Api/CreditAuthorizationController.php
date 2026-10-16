<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Actions\AuthorizeCreditAction;
use App\Domains\Credit\Actions\ProposeCreditAction;
use App\Domains\Credit\DTOs\AuthorizeCreditData;
use App\Domains\Credit\DTOs\ProposeCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\AuthorizeCreditRequest;
use App\Http\Requests\Credit\ProposeCreditRequest;
use Illuminate\Http\Request;

final class CreditAuthorizationController extends Controller
{
    public function show(Request $request, CreditAccess $access, CreditAuthorization $service, string $id)
    {
        return response()->json($service->payload($request->user(), $access->query($request->user())->findOrFail($id)));
    }

    public function propose(ProposeCreditRequest $request, ProposeCreditAction $action, string $id)
    {
        $loan = $action->execute($request->user(), $id, ProposeCreditData::fromArray($request->validated()));

        return response()->json(['status' => $loan->authorization_status, 'version' => $loan->authorization_version]);
    }

    public function decide(AuthorizeCreditRequest $request, AuthorizeCreditAction $action, string $id)
    {
        $loan = $action->execute($request->user(), $id, AuthorizeCreditData::fromArray($request->validated()));

        return response()->json(['status' => $loan->authorization_status, 'version' => $loan->authorization_version]);
    }
}
