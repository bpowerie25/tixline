<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'color', 'tenant_id'];

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class);
    }
}
