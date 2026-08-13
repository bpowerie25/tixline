<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SpamFilterEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'spam_filters';

    protected $fillable = ['type', 'value', 'reason', 'tenant_id'];

    public static function blocklist(): array
    {
        return static::where('type', 'blocklist')->pluck('value')->toArray();
    }

    public static function allowlist(): array
    {
        return static::where('type', 'allowlist')->pluck('value')->toArray();
    }
}
