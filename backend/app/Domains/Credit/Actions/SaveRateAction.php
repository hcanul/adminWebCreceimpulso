<?php

namespace App\Domains\Credit\Actions;

use App\Domains\Credit\Services\CreditAccess;
use App\Domains\Credit\Services\CreditCalculator;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveRateAction
{
    public function execute(User $actor, array $data, ?string $id): Rate
    {
        app(CreditAccess::class)->authorize($actor, 'credit.rates');
        if (isset($data['min_amount'],$data['max_amount']) && CreditCalculator::scaled($data['min_amount']) > CreditCalculator::scaled($data['max_amount'])) {
            throw ValidationException::withMessages(['max_amount' => 'El máximo debe ser mayor o igual al mínimo.']);
        }
        foreach (['min_amount', 'max_amount'] as $key) {
            if (isset($data[$key]) && CreditCalculator::scaled($data[$key]) <= 0) {
                throw ValidationException::withMessages([$key => 'Indica un importe mayor a cero o deja el límite vacío.']);
            }
        }
        app(CreditCalculator::class)->estimate(100000, $data['rate_factor']);

        return DB::transaction(function () use ($actor, $data, $id) {
            $rate = $id ? Rate::lockForUpdate()->findOrFail($id) : new Rate;
            $before = $rate->exists ? $rate->only(['name', 'cicle', 'min_amount', 'max_amount', 'rate_factor', 'is_active', 'calculation_method']) : null;
            $rate->fill($data + ['calculation_method' => 'weekly_per_thousand'])->save();
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'rate', 'subject_id' => $rate->id, 'action' => $id ? 'updated' : 'created', 'changes' => json_encode(['before' => $before, 'after' => $rate->only(['name', 'cicle', 'min_amount', 'max_amount', 'rate_factor', 'is_active', 'calculation_method'])]), 'created_at' => now()]);

            return $rate;
        });
    }
}
