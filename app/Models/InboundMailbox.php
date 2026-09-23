<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundMailbox extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'imap_host', 'imap_port', 'imap_encryption',
        'imap_username', 'imap_password', 'imap_folder',
        'poll_interval', 'delete_after_process', 'team_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'imap_password' => 'encrypted',
            'is_active' => 'boolean',
            'delete_after_process' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
