<?php

namespace App\Domains\Identity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FolioSequence
{
    public function next(string $scope, string $kind): int
    {
        return DB::transaction(function () use ($scope, $kind) {
            DB::table('folio_sequences')->insertOrIgnore(['id' => (string) Str::uuid(), 'scope' => $scope, 'kind' => $kind, 'last_number' => 0]);
            $row = DB::table('folio_sequences')->where('scope', $scope)->where('kind', $kind)->lockForUpdate()->first();
            $next = (int) $row->last_number + 1;
            DB::table('folio_sequences')->where('id', $row->id)->update(['last_number' => $next]);

            return $next;
        }, 3);
    }

    public function branchCode(): string
    {
        return 'S'.str_pad((string) $this->next('global', 'branch'), 3, '0', STR_PAD_LEFT);
    }

    public function issue(string $branchId, string $kind): string
    {
        $code = DB::table('branches')->where('id', $branchId)->value('folio_code');
        if (! $code) {
            throw new \LogicException('La sucursal no tiene código de folios.');
        }
        $prefix = match ($kind) {
            'client' => 'CLI','credit' => 'CRE','closing' => 'COR',default => throw new \InvalidArgumentException('Tipo de folio inválido.')
        };

        return $prefix.'-'.$code.'-'.str_pad((string) $this->next($branchId, $kind), 6, '0', STR_PAD_LEFT);
    }
}
