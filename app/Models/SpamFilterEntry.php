<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpamFilterEntry extends Model
{
    protected $table = 'spam_filters';

    protected $fillable = ['type', 'value', 'reason'];

    public static function blocklist(): array
    {
        return static::where('type', 'blocklist')->pluck('value')->toArray();
    }

    public static function allowlist(): array
    {
        return static::where('type', 'allowlist')->pluck('value')->toArray();
    }
}
