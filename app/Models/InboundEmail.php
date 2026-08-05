<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmail extends Model
{
    protected $fillable = ['message_id', 'payload', 'status', 'auth_results', 'result', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'auth_results' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
