<?php

namespace App\Http\Controllers\Api;

use App\Domains\Branches\Actions\SaveBranchAction;
use App\Domains\Branches\DTOs\BranchData;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

final class BranchController extends Controller
{
    private function authorizeActor(Request $r): void
    {
        $u = $r->user();
        abort_unless($u->is_active && ! $u->deleted_at && $u->hasAnyRole(['Super Admin', 'Gerente General']), 403);
    }

    public function index(Request $r)
    {
        $this->authorizeActor($r);
        $v = $r->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:all,active,inactive', 'page' => 'sometimes|integer|min:1|max:1000000']);
        $query = Branch::query();
        if ($search = trim($v['search'] ?? '')) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("city LIKE ? ESCAPE '!'", [$term])->orWhereRaw("folio_code LIKE ? ESCAPE '!'", [$term]));
        }
        if (($v['status'] ?? 'all') !== 'all') {
            $query->where('is_active', ($v['status'] ?? 'all') === 'active');
        }

        return response()->json($query->orderBy('name')->orderBy('id')->paginate(20))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $r, SaveBranchAction $action, ?string $id = null)
    {
        $this->authorizeActor($r);
        foreach (['name', 'city', 'phone', 'email', 'address'] as $field) {
            if (is_string($r->input($field))) {
                $r->merge([$field => trim($r->input($field)) !== '' ? trim($r->input($field)) : null]);
            }
        }
        $v = $r->validate(['id' => $id ? 'prohibited' : 'required|uuid', 'version' => $id ? 'required|integer|min:0' : 'prohibited', 'name' => 'required|string|max:255', 'city' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:254', 'address' => 'nullable|string|max:2000', 'is_active' => 'required|boolean', 'folio_code' => 'prohibited']);
        $v = array_replace(['city' => null, 'phone' => null, 'email' => null, 'address' => null], $v);
        $v['is_active'] = (bool) $v['is_active'];
        if ($id) {
            $v['version'] = (int) $v['version'];
        }

        return response()->json($action->execute($r->user(), new BranchData($v), $id), $id ? 200 : 201)->header('Cache-Control', 'private, no-store');
    }
}
