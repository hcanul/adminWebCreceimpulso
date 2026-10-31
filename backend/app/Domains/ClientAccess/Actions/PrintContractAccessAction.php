<?php

namespace App\Domains\ClientAccess\Actions;

use App\Domains\ClientAccess\Services\ClientAccess;
use App\Domains\Documents\Services\DocumentAccess;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PrintContractAccessAction
{
    public function execute(User $actor, string $id): string
    {
        return DB::transaction(function () use ($actor, $id) {
            $access = app(DocumentAccess::class);
            $document = $access->document($actor, $id);
            abort_unless(hash_equals($document->sha256, hash('sha256', $document->html)), 409, 'Documento inválido.');
            if ($document->kind !== 'contract_review') {
                return $document->html;
            }
            $loan = $access->source($actor, 'credit', $document->source_id, true);
            abort_unless(in_array($loan->status, ['aprobado', 'entregado'], true) && $loan->approved_at && $loan->approved_snapshot && $loan->authorization_version === $document->source_version, 409, 'Imprime el contrato de la autorización vigente.');
            abort_unless(str_contains($document->html, '</body>'), 409, 'El formato del contrato requiere revisión.');
            $rows = [];
            // Lock clients in stable order to serialize concurrent prints and activation.
            $ids = array_column($loan->approved_snapshot['members'], 'client_id');
            sort($ids);
            foreach ($ids as $clientId) {
                $client = app(ClientAccess::class)->clients($actor)->whereKey($clientId)->lockForUpdate()->firstOrFail();
                abort_unless($client->branch_id === $loan->branch_id, 409);
                $account = ClientAccount::where('client_id', $clientId)->first();
                $password = null;
                if (! $account) {
                    $password = 'Ci'.bin2hex(random_bytes(10));
                    $account = ClientAccount::create(['client_id' => $clientId, 'password' => $password, 'must_change_password' => true, 'temporary_password_expires_at' => now()->addDays(7)]);
                    DB::table('client_activation_codes')->where('client_id', $clientId)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
                    app(ClientAccess::class)->audit($clientId, $actor->id, 'contract_temporary_password_issued', $id);
                }
                $rows[] = ['name' => trim($client->first_name.' '.$client->last_name), 'folio' => $client->folio, 'password' => $password, 'expires' => $password ? $account->temporary_password_expires_at->timezone('America/Cancun')->format('d/m/Y H:i') : null];
            }
            $sheet = view('documents.client-access-sheet', ['rows' => $rows])->render();

            return str_replace('</body>', $sheet.'</body>', $document->html);
        }, 3);
    }
}
