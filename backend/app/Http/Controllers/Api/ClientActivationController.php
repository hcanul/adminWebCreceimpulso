<?php

namespace App\Http\Controllers\Api;

use App\Domains\ClientAccess\Actions\ActivateClientAccountAction;
use App\Domains\ClientAccess\Actions\IssueClientActivationAction;
use App\Domains\ClientAccess\Services\ClientAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final class ClientActivationController extends Controller
{
    public function search(Request $request, ClientAccess $access)
    {
        $query = $access->clients($request->user());
        $data = $request->validate(['identifier' => ['required', 'string', 'min:3', 'max:80']]);
        $identifier = mb_strtoupper(trim($data['identifier']));
        // Exact lookup avoids exposing broad lists of personal information.
        $clients = $query->where(fn ($q) => $q->where('curp', $identifier)->orWhere('folio', $identifier))->with('branch:id,name')->limit(2)->get(['id', 'folio', 'first_name', 'last_name', 'branch_id']);

        return response()->json(['clients' => $clients])->header('Cache-Control', 'no-store');
    }

    public function issue(Request $request, string $client, IssueClientActivationAction $action)
    {
        $request->validate(['identity_verified' => ['required', 'accepted']]);

        return response()->json($action->execute($request->user(), $client), 201)->header('Cache-Control', 'no-store');
    }

    public function recover(Request $request, string $client, IssueClientActivationAction $action)
    {
        $request->validate(['identity_verified' => ['required', 'accepted']]);

        return response()->json($action->execute($request->user(), $client, true), 201)->header('Cache-Control', 'no-store');
    }

    public function activate(Request $request, ActivateClientAccountAction $action)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'], 'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $action->execute($data['code'], $data['password']);

        return response()->json(['message' => 'Acceso configurado correctamente.'])->header('Cache-Control', 'no-store');
    }
}
