<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'color', 'description', 'tenant_id'];

    public static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class)
            ->withPivot(['confidence', 'is_ai_suggested', 'is_confirmed']);
    }
}
