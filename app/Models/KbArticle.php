<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticle extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'excerpt', 'body',
        'status', 'sort_order', 'views', 'published_at', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
