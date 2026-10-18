<?php

namespace App\Http\Controllers\Api;

use App\Domains\Guarantees\Actions\ApplyGuaranteeAction;
use App\Domains\Guarantees\Services\GuaranteeBalance;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

final class GuaranteeController extends Controller
{
    public function show(Request $r, GuaranteeBalance $service, string $id)
    {
        return response()->json($service->payload($r->user(), $id))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $r, ApplyGuaranteeAction $action, GuaranteeBalance $service, string $id)
    {
        $v = $r->validate(['id' => 'required|uuid', 'version' => 'required|string|size:64', 'reason' => 'required|string|min:10|max:2000']);
        $v['reason'] = trim($v['reason']);
        abort_unless(mb_strlen($v['reason']) >= 10, 422, 'Describe el motivo de la aplicación.');
        try {
            $action->execute($r->user(), $id, $v);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'La operación ya se registró. Actualiza antes de reintentar.');
        }

        return response()->json($service->payload($r->user(), $id));
    }

    public function reverse(Request $r, ApplyGuaranteeAction $action, string $id)
    {
        $v = $r->validate(['reason' => 'required|string|min:10|max:2000']);
        $reason = trim($v['reason']);
        abort_unless(mb_strlen($reason) >= 10, 422, 'Describe el motivo del reverso.');
        $action->reverse($r->user(), $id, $reason);

        return response()->json(['reversed' => true]);
    }
}
