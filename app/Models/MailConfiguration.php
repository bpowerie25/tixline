<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailConfiguration extends Model
{
    protected $fillable = [
        'mailer', 'host', 'port', 'encryption',
        'username', 'password',
        'from_address', 'from_name', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
