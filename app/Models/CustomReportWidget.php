<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomReportWidget extends Model
{
    protected $fillable = [
        'custom_report_id',
        'widget_type',
        'chart_type',
        'title',
        'grid_x',
        'grid_y',
        'grid_w',
        'grid_h',
        'filters',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'grid_x' => 'integer',
            'grid_y' => 'integer',
            'grid_w' => 'integer',
            'grid_h' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function customReport(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class);
    }
}
