<?php

namespace App\Http\Controllers\Api;

use App\Domains\Administration\Actions\SaveRoleAction;
use App\Domains\Administration\Actions\SaveUserAction;
use App\Domains\Administration\DTOs\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SaveUserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AdministrationController extends Controller
{
    public function options()
    {
        return response()->json([
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::where('guard_name', 'web')->whereIn('name', ['intake.access', 'intake.submit', 'promoters.register', 'credit.view', 'credit.create', 'credit.rates', 'credit.review', 'credit.propose', 'credit.authorize', 'credit.disburse', 'credit.collect', 'credit.reverse', 'cash.view', 'cash.close', 'cash.audit'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function users(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);

        return response()->json(User::whereNull('deleted_at')->when($data['search'] ?? '', fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))->with('roles:id,name')->orderBy('name')->paginate(20, ['id', 'name', 'email', 'branch_id', 'is_active']));
    }

    public function saveUser(SaveUserRequest $request, SaveUserAction $action, ?string $userId = null)
    {
        return response()->json($action->execute($request->user(), UserData::fromArray($request->validated()), $userId), $userId ? 200 : 201);
    }

    public function saveRole(Request $request, SaveRoleAction $action, ?string $roleId = null)
    {
        $data = $request->validate([
            'name' => [$roleId ? 'prohibited' : 'required', 'string', 'max:80', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(['intake.access', 'intake.submit', 'promoters.register', 'credit.view', 'credit.create', 'credit.rates', 'credit.review', 'credit.propose', 'credit.authorize', 'credit.disburse', 'credit.collect', 'credit.reverse', 'cash.view', 'cash.close', 'cash.audit'])],
        ]);

        return response()->json($action->execute($request->user(), $data, $roleId), $roleId ? 200 : 201);
    }
}
