<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiTaggingConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'is_enabled', 'provider', 'model', 'api_key',
        'auto_apply', 'confidence_threshold', 'flag_miscategorised',
        'tag_on_create',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'auto_apply' => 'boolean',
            'flag_miscategorised' => 'boolean',
            'tag_on_create' => 'boolean',
            'confidence_threshold' => 'decimal:3',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_enabled', true)->first();
    }

    public static function providerOptions(): array
    {
        return [
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.5-pro'],
            ],
            'claude' => [
                'name' => 'Anthropic Claude',
                'models' => ['claude-sonnet-4-20250514', 'claude-haiku-4-20250414', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4.1-nano'],
            ],
            'mistral' => [
                'name' => 'Mistral AI',
                'models' => ['mistral-large-latest', 'mistral-small-latest'],
            ],
        ];
    }
}
