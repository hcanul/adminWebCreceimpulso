<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanParticipation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['branch_id', 'loan_application_id', 'client_id', 'role_in_group', 'amount_requested', 'amount_approved'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function loan()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $participation) {
            if ($participation->isDirty('amount_approved') && $participation->loan()->whereNotNull('approved_at')->exists()) {
                throw new \LogicException('El monto autorizado es inmutable.');
            }
            if ($participation->isDirty(['amount_requested', 'loan_application_id', 'client_id', 'role_in_group', 'branch_id'])) {
                throw new \LogicException('La participación solicitada original es inmutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['amount_requested' => 'decimal:2', 'amount_approved' => 'decimal:2'];
    }
}
