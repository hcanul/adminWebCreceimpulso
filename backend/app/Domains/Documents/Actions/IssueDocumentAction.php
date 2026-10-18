<?php

namespace App\Domains\Documents\Actions;

use App\Domains\Credit\Services\CreditCalculator;
use App\Domains\Credit\Services\CreditDisbursement;
use App\Domains\Documents\Services\DocumentAccess;
use App\Domains\Intake\Services\ApplicationFields;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueDocumentAction
{
    public function execute(User $actor, string $type, string $sourceId, string $kind): string
    {
        return DB::transaction(function () use ($actor, $type, $sourceId, $kind) {
            $source = app(DocumentAccess::class)->source($actor, $type, $sourceId, true);
            abort_unless(in_array($kind, $type === 'file' ? ['dossier', 'installation'] : ['authorization', 'schedule'], true), 422, 'Formato no disponible para este registro.');
            if ($type === 'credit') {
                abort_unless($source->approved_snapshot && $source->approved_at, 409, 'Primero autoriza el crédito.');
            }
            $version = $type === 'file' ? $source->version : $source->authorization_version;
            $key = ['source_type' => $type, 'source_id' => $sourceId, 'kind' => $kind, 'source_version' => $version, 'template_version' => 1];
            $existing = DB::table('document_versions')->where($key)->value('id');
            if ($existing) {
                return $existing;
            }
            $team = Team::withTrashed()->with(['branch', 'promoter'])->findOrFail($source->team_id);
            $snapshot = ['branch' => $team->branch?->name, 'group' => $team->name, 'promoter' => $team->promoter?->name, 'status' => $source->status, 'source_version' => $version];
            if ($type === 'file') {
                $fields = $source->fields ?? [];
                $fields['city_id'] = $snapshot['branch'];
                $fields['team_id'] = $snapshot['group'];
                $fields['promoter_id'] = $snapshot['promoter'];
                $fields['ncliente'] = $source->client?->folio;
                $sections = [];
                foreach (app(ApplicationFields::class)->schema() as $field) {
                    if ($field['name'] === 'nCredito') {
                        continue;
                    }
                    $value = $fields[$field['name']] ?? '';
                    foreach ($field['options'] as $option) {
                        if ((string) $option['value'] === (string) $value) {
                            $value = $option['label'];
                            break;
                        }
                    }
                    $sections[$field['section']][] = ['label' => $field['label'], 'value' => $value];
                }
                $snapshot += ['fields' => $fields, 'sections' => $sections, 'client' => trim(($fields['pNombre'] ?? '').' '.($fields['sNombre'] ?? '').' '.($fields['aPaterno'] ?? '').' '.($fields['aMaterno'] ?? '')), 'folio' => $source->client?->folio];
            } else {
                $snapshot += ['terms' => $source->approved_snapshot, 'folio' => $source->folio, 'approved_at' => $source->approved_at->timezone('America/Cancun')->toDateTimeString()];
            }
            if ($kind === 'schedule') {
                $firstDue = $source->approved_snapshot['first_due_on'] ?? null;
                abort_unless($firstDue, 409, 'La autorización histórica no contiene la fecha del primer pago. Consulta el estado de cuenta del crédito.');
                $rows = app(CreditDisbursement::class)->schedule($source->approved_snapshot, $firstDue);
                $weeks = [];
                foreach ($rows as $row) {
                    $number = $row['number'];
                    $weeks[$number] ??= ['number' => $number, 'due_on' => $row['due_on'], 'cents' => 0];
                    $weeks[$number]['cents'] += CreditCalculator::scaled($row['amount_due']);
                }
                foreach ($weeks as &$week) {
                    $week['amount'] = CreditCalculator::money($week['cents']);
                    unset($week['cents']);
                }
                unset($week);
                $snapshot['schedule'] = array_values($weeks);
            }
            $id = (string) Str::uuid();
            $issued = now('America/Cancun')->toDateTimeString();
            $html = view('documents.record', ['snapshot' => $snapshot, 'kind' => $kind, 'id' => $id, 'issued' => $issued])->render();
            DB::table('document_versions')->insert($key + ['id' => $id, 'branch_id' => $source->branch_id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'html' => $html, 'sha256' => hash('sha256', $html), 'created_at' => now()]);

            return $id;
        }, 3);
    }
}
