<?php

namespace App\Models;

use App\Models\Concerns\HasBranchFolio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use HasBranchFolio, HasUuids, SoftDeletes;

    protected function folioKind(): string
    {
        return 'credit';
    }

    protected $hidden = ['request_hash', 'review_checks', 'authorization_proposal'];

    protected $fillable = ['branch_id', 'team_id', 'promoter_id', 'rate_id', 'total_requested', 'total_approved', 'status', 'pacted_date'];

    protected function casts(): array
    {
        return ['authorization_version' => 'integer', 'authorization_proposal' => 'array', 'approved_snapshot' => 'array', 'approved_at' => 'datetime', 'review_version' => 'integer', 'review_checks' => 'array', 'request_snapshot' => 'array', 'requested_at' => 'datetime', 'pacted_date' => 'date', 'total_requested' => 'decimal:2', 'total_approved' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $loan) {
            if ($loan->getRawOriginal('approved_at') !== null && $loan->isDirty(['total_approved', 'approved_snapshot', 'approved_by', 'approved_at', 'disbursement_method', 'repayment_method', 'authorization_proposal', 'authorization_status', 'authorization_version'])) {
                throw new \LogicException('Las condiciones autorizadas son inmutables.');
            }
            if ($loan->getRawOriginal('request_snapshot') !== null && $loan->isDirty(['total_requested', 'team_id', 'rate_id', 'promoter_id', 'request_snapshot', 'request_hash', 'requested_at', 'created_by'])) {
                throw new \LogicException('La solicitud original es inmutable.');
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function participations()
    {
        return $this->hasMany(LoanParticipation::class);
    }
}
