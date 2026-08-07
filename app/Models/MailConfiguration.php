<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailConfiguration extends Model
{
    protected $fillable = [
        'mailer', 'host', 'port', 'encryption',
        'username', 'password',
        'from_address', 'from_name', 'is_active',
        'inbound_method', 'imap_host', 'imap_port', 'imap_encryption',
        'imap_username', 'imap_password', 'imap_folder',
        'imap_poll_interval', 'imap_delete_after_process',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'imap_password' => 'encrypted',
            'is_active' => 'boolean',
            'imap_delete_after_process' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
