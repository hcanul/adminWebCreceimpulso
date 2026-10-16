<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientApplication extends Model
{
    use HasUuids;

    // Tabla histórica de captura: representa el expediente, no una solicitud financiera.
    protected $hidden = ['amount_requested'];

    protected $fillable = ['branch_id', 'team_id', 'client_id', 'created_by', 'status', 'fields', 'version', 'submitted_at'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'amount_requested' => 'decimal:2', 'version' => 'integer', 'submitted_at' => 'datetime'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
