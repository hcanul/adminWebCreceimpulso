<?php

namespace App\Http\Controllers\Api;

use App\Domains\Cash\Actions\CloseCashAction;
use App\Domains\Cash\DTOs\CloseCashData;
use App\Domains\Cash\Services\CashClosings;
use App\Domains\Intake\Services\IntakeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\CloseCashRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CashClosingController extends Controller
{
    public function options(Request $request, CashClosings $s)
    {
        $s->authorize($request->user());
        $q = DB::table('branches')->orderBy('name');
        if (! app(IntakeAccess::class)->global($request->user())) {
            $q->where('id', $request->user()->branch_id ?? '');
        }

        return response()->json(['branches' => $q->get(['id', 'name']), 'today' => now('America/Cancun')->toDateString()]);
    }

    public function index(Request $request, CashClosings $s)
    {
        $v = $request->validate(['branch_id' => 'nullable|uuid', 'business_date' => 'nullable|date_format:Y-m-d', 'page' => 'sometimes|integer|min:1|max:100000']);

        return response()->json($s->query($request->user())->when($v['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))->when($v['business_date'] ?? null, fn ($q, $date) => $q->where('business_date', $date))->orderByDesc('created_at')->orderByDesc('folio')->paginate(20)->through(fn ($r) => $s->normalize($r)));
    }

    public function preview(Request $request, CashClosings $s)
    {
        $v = $request->validate(['branch_id' => 'required|uuid', 'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cancun')->toDateString()], 'page' => 'sometimes|integer|min:1|max:100000']);

        return response()->json($s->preview($request->user(), $v['branch_id'], $v['business_date'], (int) $request->input('page', 1)));
    }

    public function show(Request $request, CashClosings $s, string $id)
    {
        $request->validate(['page' => 'sometimes|integer|min:1|max:100000', 'adjustments_page' => 'sometimes|integer|min:1|max:100000']);

        return response()->json($s->detail($request->user(), $id, (int) $request->input('page', 1), (int) $request->input('adjustments_page', 1)));
    }

    public function store(CloseCashRequest $request, CloseCashAction $action, CashClosings $s)
    {
        $id = $action->execute($request->user(), CloseCashData::fromArray($request->validated()));

        return response()->json($s->detail($request->user(), $id));
    }

    public function report(Request $request, CashClosings $s, string $id)
    {
        return response()->json($s->report($request->user(), $id))
            ->header('Cache-Control', 'private, no-store');
    }
}
