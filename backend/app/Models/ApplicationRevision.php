<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApplicationRevision extends Model
{
    use HasUuids;

    protected $fillable = ['application_id', 'actor_id', 'version', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
