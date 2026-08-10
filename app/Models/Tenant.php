<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'domain', 'logo_url', 'favicon_url', 'header_height',
        'primary_color', 'secondary_color', 'accent_color',
        'header_bg_color', 'header_text_color', 'sidebar_bg_color',
        'custom_css', 'font_family', 'portal_title', 'portal_welcome_text',
        'support_email', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function cssVariables(): string
    {
        return implode('; ', array_filter([
            "--color-primary: {$this->primary_color}",
            "--color-secondary: {$this->secondary_color}",
            "--color-accent: {$this->accent_color}",
            "--header-bg: {$this->header_bg_color}",
            "--header-text: {$this->header_text_color}",
            "--sidebar-bg: {$this->sidebar_bg_color}",
            $this->font_family ? "--font-family: {$this->font_family}" : null,
        ]));
    }
}
