<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Actions\CollectCreditAction;
use App\Domains\Credit\DTOs\CollectCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCollections;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\CollectCreditRequest;
use Illuminate\Http\Request;

final class CreditCollectionController extends Controller
{
    public function show(Request $request, CreditAccess $access, CreditCollections $service, string $id)
    {
        $request->validate(['page' => 'sometimes|integer|min:1|max:100000']);
        CreditCollections::authorizeRead($request->user());

        return response()->json($service->payload($request->user(), $access->query($request->user())->findOrFail($id), (int) $request->input('page', 1)));
    }

    public function store(CollectCreditRequest $request, CollectCreditAction $action, CreditCollections $service, string $id)
    {
        $loan = $action->execute($request->user(), $id, CollectCreditData::fromArray($request->validated()));

        return response()->json($service->payload($request->user(), $loan));
    }
}
