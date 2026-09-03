<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'filename', 'original_filename', 'mime_type', 'size', 'path',
    ];

    protected $appends = ['is_image'];

    public function getIsImageAttribute(): bool
    {
        return in_array($this->mime_type, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        ]);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
