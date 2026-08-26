<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InboundEmail extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'message_id', 'payload', 'status', 'auth_results', 'result', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'auth_results' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
