<?php

namespace App\Domains\Credit\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreditDisbursement
{
    public function schedule(array $snapshot, string $firstDue): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $firstDue);
        $rows = [];
        foreach ($snapshot['members'] as $member) {
            for ($week = 1; $week <= 16; $week++) {
                $rows[] = ['client_id' => $member['client_id'], 'number' => $week, 'due_on' => $first->addWeeks($week - 1)->toDateString(), 'amount_due' => $member['estimate']['weekly_payment']];
            }
        }

        return $rows;
    }

    public function payload(User $actor, LoanApplication $loan): array
    {
        $record = DB::table('credit_disbursements')->where('loan_application_id', $loan->id)->first();
        if ($record) {
            unset($record->request_hash);
            foreach (['capital', 'guarantee_retained', 'net_delivered'] as $key) {
                $record->$key = CreditCalculator::money(CreditCalculator::scaled((string) $record->$key));
            }
            $record->snapshot = json_decode($record->snapshot, true);
            if (! $actor->hasRole('Super Admin') && ! $actor->can('credit.disburse')) {
                unset($record->bank_reference, $record->bank_name, $record->notes);
            }
        }

        return ['record' => $record, 'installments' => DB::table('loan_installments')->where('loan_application_id', $loan->id)->orderBy('number')->orderBy('client_id')->get()->map(function ($row) {
            foreach (['amount_due', 'amount_paid'] as $key) {
                $row->$key = CreditCalculator::money(CreditCalculator::scaled((string) $row->$key));
            }

            return $row;
        })];
    }
}
