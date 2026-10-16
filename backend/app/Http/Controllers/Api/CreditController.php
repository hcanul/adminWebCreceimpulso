<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Actions\CreateCreditAction;
use App\Domains\Credit\Actions\ReviewCreditAction;
use App\Domains\Credit\Actions\SaveRateAction;
use App\Domains\Credit\DTOs\CreateCreditData;
use App\Domains\Credit\DTOs\ReviewCreditData;
use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditEligibility;
use App\Domains\Credit\Services\CreditReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\CreateCreditRequest;
use App\Http\Requests\Credit\ReviewCreditRequest;
use App\Models\Rate;
use Illuminate\Http\Request;

final class CreditController extends Controller
{
    public function index(Request $request, CreditAccess $access)
    {
        $request->validate(['search' => 'nullable|string|max:100', 'financial_status' => 'nullable|in:aprobado,entregado,pagado', 'review_status' => 'nullable|in:pending,in_review,returned,ready_for_authorization', 'authorization_status' => 'nullable|in:awaiting_proposal,pending_authorization,returned,authorized']);
        $query = $access->query($request->user());
        if ($request->filled('review_status')) {
            $query->where('status', 'pendiente')->whereNotNull('request_snapshot')->where('review_status', $request->input('review_status'));
        }
        if ($request->filled('authorization_status')) {
            $query->whereNotNull('request_snapshot')->where('review_status', 'ready_for_authorization')->where('authorization_status', $request->input('authorization_status'));
        }
        if ($request->filled('financial_status')) {
            $query->whereNotNull('approved_snapshot')->where('status', $request->input('financial_status'));
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('folio', 'like', '%'.$request->string('search').'%')->orWhereHas('team', fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')));
        }

        return response()->json($query->with(['team:id,name', 'branch:id,name'])->latest()->paginate(20, ['id', 'folio', 'team_id', 'branch_id', 'total_requested', 'total_approved', 'status', 'review_status', 'authorization_status', 'requested_at', 'created_at', 'disbursement_method']));
    }

    public function show(Request $request, CreditAccess $access, string $id)
    {
        return response()->json($access->query($request->user())->with(['team:id,name', 'branch:id,name', 'participations.client:id,folio,first_name,last_name'])->findOrFail($id));
    }

    public function review(Request $request, CreditAccess $access, CreditReview $review, string $id)
    {
        return response()->json($review->payload($request->user(), $access->query($request->user())->findOrFail($id)));
    }

    public function updateReview(ReviewCreditRequest $request, ReviewCreditAction $action, string $id)
    {
        $loan = $action->execute($request->user(), $id, ReviewCreditData::fromArray($request->validated()));

        return response()->json(['status' => $loan->review_status, 'version' => $loan->review_version]);
    }

    public function context(Request $request, CreditEligibility $eligibility, string $teamId)
    {
        return response()->json($eligibility->context($request->user(), $teamId));
    }

    public function store(CreateCreditRequest $request, CreateCreditAction $action)
    {
        return response()->json($action->execute($request->user(), CreateCreditData::fromArray($request->validated())), 201);
    }

    public function rates(Request $request, CreditAccess $access)
    {
        $access->authorize($request->user());
        $request->validate(['search' => 'nullable|string|max:100', 'financial_status' => 'nullable|in:aprobado,entregado,pagado', 'management' => 'sometimes|boolean']);
        $query = Rate::query();
        if ($request->boolean('management')) {
            $access->authorize($request->user(), 'credit.rates');
        } else {
            $query->where('is_active', true)->where('calculation_method', 'weekly_per_thousand');
        }

        return response()->json($query->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))->orderBy('name')->paginate(20));
    }

    public function saveRate(Request $request, CreditAccess $access, SaveRateAction $action, ?string $id = null)
    {
        $access->authorize($request->user(), 'credit.rates');
        $data = $request->validate(['name' => 'required|string|max:100', 'cicle' => 'required|integer|min:1|max:100', 'min_amount' => ['nullable', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'max_amount' => ['nullable', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'rate_factor' => ['required', 'string', 'regex:/^\d{1,4}(?:\.\d{1,4})?$/D'], 'is_active' => 'required|boolean']);

        return response()->json($action->execute($request->user(), $data, $id), $id ? 200 : 201);
    }
}
