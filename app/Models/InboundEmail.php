<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmail extends Model
{
    protected $fillable = ['message_id', 'payload', 'status', 'result', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
