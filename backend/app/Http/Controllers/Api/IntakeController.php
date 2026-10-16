<?php

namespace App\Http\Controllers\Api;

use App\Domains\Intake\Actions\AssignCommitteeAction;
use App\Domains\Intake\Actions\LookupClientAction;
use App\Domains\Intake\Actions\RegisterPromoterAction;
use App\Domains\Intake\Actions\SaveApplicationAction;
use App\Domains\Intake\Actions\SaveTeamAction;
use App\Domains\Intake\DTOs\SaveApplicationData;
use App\Domains\Intake\DTOs\SaveTeamData;
use App\Domains\Intake\Rules\Curp;
use App\Domains\Intake\Services\IntakeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\SaveApplicationRequest;
use App\Models\Branch;
use App\Models\ClientApplication;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class IntakeController extends Controller
{
    public function options(Request $request, IntakeAccess $access)
    {
        $actor = $request->user();
        $branches = Branch::where('is_active', true);
        $promoters = Promoter::assignable();
        $candidates = User::where('is_active', true)->whereNull('deleted_at')->whereNotNull('branch_id')->whereHas('roles', fn ($q) => $q->where('name', 'Promotor')->where('guard_name', 'web'))->whereNotIn('id', Promoter::select('id'));
        if (! $access->global($actor)) {
            $branches->whereKey($actor->branch_id ?? '');
            $promoters->where('branch_id', $actor->branch_id ?? '');
            $candidates->where('branch_id', $actor->branch_id ?? '');
        }
        if (! $actor->hasRole('Super Admin') && ! $actor->can('promoters.register')) {
            $candidates->whereRaw('1 = 0');
        }
        if ($access->field($actor) && $actor->hasRole('Promotor')) {
            $promoters->whereKey($actor->id);
        }

        return response()->json(['branches' => $branches->orderBy('name')->get(['id', 'name']), 'promoters' => $promoters->get(['id', 'branch_id']), 'promoter_candidates' => $candidates->get(['id', 'branch_id', 'name'])]);
    }

    public function registerPromoter(Request $request, RegisterPromoterAction $action)
    {
        $data = $request->validate(['user_id' => 'required|uuid']);

        return response()->json($action->execute($request->user(), $data['user_id']), 201);
    }

    public function searchTeams(Request $request, IntakeAccess $access)
    {
        $data = $request->validate(['search' => 'required|string|min:2|max:100', 'branch_id' => 'nullable|uuid']);
        $actor = $request->user();
        $branch = $actor->branch_id;
        if ($branch) {
            abort_if(isset($data['branch_id']) && $data['branch_id'] !== $branch, 403);
        } else {
            abort_unless($access->global($actor), 403, 'Tu cuenta requiere una sucursal.');
            $branch = $data['branch_id'] ?? null;
        }
        abort_unless($branch && Branch::whereKey($branch)->where('is_active', true)->exists(), 422, 'Selecciona una sucursal activa.');
        // Escape LIKE wildcards: the typed text is a literal name fragment.
        $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($data['search']));
        $rows = $access->teams($actor)->where('branch_id', $branch)
            ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$search.'%'])
            ->with(['branch:id,name', 'promoter:id,branch_id'])->orderBy('name')->orderBy('id')->limit(16)->get();

        return response()->json(['data' => $rows->take(15)->values(), 'has_more' => $rows->count() > 15]);
    }

    public function teams(Request $request, IntakeAccess $access)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        return response()->json($access->teams($request->user())->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))->with(['branch:id,name', 'promoter:id,branch_id'])->withCount('memberships')->latest()->paginate(20));
    }

    public function saveTeam(Request $request, IntakeAccess $access, SaveTeamAction $action, ?string $teamId = null)
    {
        if (! $request->filled('promoter_id') && $request->user()->hasRole('Promotor')) {
            $request->merge(['promoter_id' => $request->user()->id]);
        }
        $data = $request->validate(['name' => 'required|string|max:150', 'branch_id' => 'required|uuid', 'promoter_id' => 'required|uuid']);
        $team = $teamId ? $access->team($request->user(), $teamId) : null;

        return response()->json($action->execute($request->user(), new SaveTeamData($data['name'], $data['branch_id'], $data['promoter_id']), $team), $team ? 200 : 201);
    }

    public function team(Request $request, IntakeAccess $access, string $teamId)
    {
        $team = $access->team($request->user(), $teamId)->load(['branch:id,name', 'promoter:id,branch_id', 'memberships.client:id,folio,curp,first_name,last_name,status']);

        return response()->json($team);
    }

    public function committee(Request $request, IntakeAccess $access, AssignCommitteeAction $action, string $teamId, string $membershipId)
    {
        $data = $request->validate(['role_in_group' => ['required', Rule::in(['presidenta', 'tesorera', 'integrante'])]]);

        return response()->json($action->execute($access->team($request->user(), $teamId), $membershipId, $data['role_in_group']));
    }

    public function lookup(Request $request, LookupClientAction $action)
    {
        if (is_string($request->input('curp'))) {
            $request->merge(['curp' => Curp::normalize($request->input('curp'))]);
        }
        $data = $request->validate(['curp' => ['required', 'string', new Curp], 'team_id' => 'required|uuid']);

        return response()->json($action->execute($request->user(), $data['curp'], $data['team_id']));
    }

    public function applications(Request $request, IntakeAccess $access)
    {
        return response()->json(ClientApplication::whereIn('team_id', $access->teams($request->user())->select('id'))->with(['client:id,folio,curp,first_name,last_name', 'team:id,name'])->latest()->paginate(20, ['id', 'team_id', 'client_id', 'status', 'updated_at']));
    }

    public function saveApplication(SaveApplicationRequest $request, SaveApplicationAction $action)
    {
        return response()->json($action->execute($request->user(), SaveApplicationData::fromArray($request->validated())));
    }
}
