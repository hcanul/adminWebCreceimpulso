<?php

namespace App\Http\Controllers\Api;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditStatement;
use App\Domains\Portfolio\Actions\AppendFollowupAction;
use App\Domains\Portfolio\Actions\ReclassifyPortfolioAction;
use App\Domains\Portfolio\DTOs\PortfolioCommand;
use App\Http\Controllers\Controller;
use App\Models\CreditFollowupEntry;
use App\Models\PortfolioEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PortfolioFollowupController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['page' => 'sometimes|integer|min:1|max:1000000', 'branch_id' => 'nullable|uuid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'author' => 'nullable|string|max:100', 'search' => 'nullable|string|max:100']);
        abort_if(isset($v['from'], $v['to']) && $v['from'] > $v['to'], 422, 'Revisa el rango de fechas.');
        $actor = $r->user();
        app(CreditAccess::class)->authorize($actor, 'credit.followup');
        $loans = app(CreditAccess::class)->query($actor)->select('id');
        $q = DB::table('credit_followup_entries as e')->whereIn('e.loan_application_id', $loans)
            ->join('loan_applications as l', 'l.id', '=', 'e.loan_application_id')
            ->join('teams as t', 't.id', '=', 'l.team_id')->join('branches as b', 'b.id', '=', 'e.branch_id')
            ->join('clients as c', 'c.id', '=', 'e.client_id')
            ->when($v['branch_id'] ?? null, fn ($q, $branch) => $q->where('e.branch_id', $branch))
            ->when($v['from'] ?? null, fn ($q, $from) => $q->where('e.created_at', '>=', CarbonImmutable::parse($from, 'America/Cancun')->utc()))
            ->when($v['to'] ?? null, fn ($q, $to) => $q->where('e.created_at', '<', CarbonImmutable::parse($to, 'America/Cancun')->addDay()->utc()));
        foreach (['author' => ['e.actor_name'], 'search' => ['l.folio', 't.name', 'c.first_name', 'c.last_name', 'c.folio']] as $filter => $columns) {
            if ($text = trim($v[$filter] ?? '')) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
                $q->where(function ($query) use ($columns, $term) {
                    foreach ($columns as $column) {
                        $query->orWhereRaw("$column LIKE ? ESCAPE '!'", [$term]);
                    }
                });
            }
        }
        $branches = DB::table('branches')->whereIn('id', app(CreditAccess::class)->query($actor)->select('branch_id'))->orderBy('name')->get(['id', 'name']);

        return response()->json(['branches' => $branches, 'entries' => $q->select(['e.id', 'e.loan_application_id', 'e.client_id', 'e.observation', 'e.actor_name', 'e.created_at', 'l.folio as credit_folio', 't.name as group', 'b.name as branch', 'c.first_name', 'c.last_name', 'c.folio as client_folio'])->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25)])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $r, string $id)
    {
        $v = $r->validate(['page' => 'sometimes|integer|min:1|max:1000000', 'client_id' => 'nullable|uuid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'author' => 'nullable|string|max:100']);
        abort_if(isset($v['from'], $v['to']) && $v['from'] > $v['to'], 422, 'Revisa el rango de fechas.');
        $actor = $r->user();
        app(CreditAccess::class)->authorize($actor, 'credit.followup');
        $loan = app(CreditAccess::class)->query($actor)->whereKey($id)->firstOrFail();
        $statement = app(CreditStatement::class)->payload($actor, $id);
        $notes = CreditFollowupEntry::where('loan_application_id', $id)
            ->when($v['client_id'] ?? null, fn ($q, $client) => $q->where('client_id', $client))
            ->when($v['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', CarbonImmutable::parse($from, 'America/Cancun')->utc()))
            ->when($v['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', CarbonImmutable::parse($to, 'America/Cancun')->addDay()->utc()));
        if ($author = trim($v['author'] ?? '')) {
            $notes->whereRaw("actor_name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $author).'%']);
        }
        $phones = DB::table('clients')->whereIn('id', collect($statement['members'])->pluck('client_id'))->pluck('phone', 'id');
        $members = collect($statement['members'])->map(fn ($m) => $m + ['phone' => $phones[$m['client_id']] ?? null]);

        return response()->json(['loan_id' => $id, 'folio' => $loan->folio, 'group' => $statement['credit']['group'], 'branch_id' => $loan->branch_id,
            'classification' => $loan->portfolio_class, 'version' => (int) $loan->portfolio_version,
            'can_manage' => $actor->hasAnyRole(['Super Admin', 'Gerente General']) && ($actor->hasRole('Super Admin') || $actor->can('credit.portfolio.manage')),
            'members' => $members, 'events' => PortfolioEvent::where('loan_application_id', $id)->orderByDesc('version')->get(),
            'notes' => $notes->orderByDesc('sequence')->paginate(25)])->header('Cache-Control', 'private, no-store');
    }

    public function classify(Request $r, string $id, ReclassifyPortfolioAction $action)
    {
        $v = $r->validate(['id' => 'required|uuid', 'version' => 'required|integer|min:0', 'classification' => ['required', Rule::in(['ordinary', 'recovery'])], 'reason' => 'required|string|min:10|max:2000']);
        $v['reason'] = trim($v['reason']);
        abort_unless(mb_strlen($v['reason']) >= 10, 422, 'Describe el motivo de la reclasificación.');
        $action->execute($r->user(), $id, new PortfolioCommand($v));

        return response()->json(['saved' => true]);
    }

    public function append(Request $r, string $id, AppendFollowupAction $action)
    {
        $v = $r->validate(['id' => 'required|uuid', 'client_id' => 'required|uuid', 'observation' => 'required|string|max:2000']);
        $v['observation'] = trim($v['observation']);
        abort_if($v['observation'] === '', 422, 'Escribe una observación.');
        $action->execute($r->user(), $id, new PortfolioCommand($v));

        return response()->json(['saved' => true]);
    }
}
