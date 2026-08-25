<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'team_id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
