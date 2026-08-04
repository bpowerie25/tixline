<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'name', 'label', 'type', 'options', 'is_required',
        'sort_order', 'conditions',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'conditions' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
