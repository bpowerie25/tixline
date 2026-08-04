<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'manager_id'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Team::class, 'department_id', 'team_id');
    }
}
