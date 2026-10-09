<?php

namespace App\Services\AiTagging;

use App\Models\AiTaggingConfig;
use App\Models\Tag;
use App\Models\Team;
use App\Models\Ticket;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Log;

class AiTagger
{
    public function tagTicket(Ticket $ticket): bool
    {
        $config = AiTaggingConfig::active();

        if (! $config) {
            return false;
        }

        $tags = Tag::pluck('name')->toArray();

        if (empty($tags)) {
            return false;
        }

        $teams = Team::pluck('name')->toArray();
        $currentTeam = $ticket->team?->name;

        $provider = $this->resolveProvider($config);

        try {
            $result = $provider->analyse(
                subject: $ticket->subject,
                body: $this->anonymise($ticket),
                availableTags: $tags,
                availableTeams: $teams,
                currentTeam: $currentTeam,
            );
        } catch (\Throwable $e) {
            Log::error('AI tagging failed', [
                'ticket_id' => $ticket->id,
                'provider' => $config->provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $this->applyResults($ticket, $result, $config);

        return true;
    }

    protected function resolveProvider(AiTaggingConfig $config): AiTaggingProvider
    {
        return match ($config->provider) {
            'gemini' => new GeminiProvider($config->api_key, $config->model),
            'claude' => new ClaudeProvider($config->api_key, $config->model),
            'openai' => new OpenAiProvider($config->api_key, $config->model),
            'mistral' => new MistralProvider($config->api_key, $config->model),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$config->provider}"),
        };
    }

    protected function anonymise(Ticket $ticket): string
    {
        $body = strip_tags(HtmlSanitizer::sanitize($ticket->body ?? ''));

        // Remove email addresses
        $body = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $body);

        // Remove phone numbers (various formats)
        $body = preg_replace('/(\+?\d{1,3}[\s\-]?)?\(?\d{2,4}\)?[\s\-]?\d{3,4}[\s\-]?\d{3,4}/', '[PHONE]', $body);

        // Remove the requester name if it appears in the body
        if ($ticket->requester_name) {
            $body = str_ireplace($ticket->requester_name, '[REQUESTER]', $body);
        }

        // Truncate very long bodies to stay within token limits
        if (mb_strlen($body) > 3000) {
            $body = mb_substr($body, 0, 3000) . "\n[TRUNCATED]";
        }

        return $body;
    }

    protected function applyResults(Ticket $ticket, array $result, AiTaggingConfig $config): void
    {
        $threshold = (float) $config->confidence_threshold;

        // Find matching tags and filter by confidence
        $tagSuggestions = collect($result['tags'])
            ->filter(fn ($t) => $t['confidence'] >= $threshold)
            ->map(function ($t) {
                $tag = Tag::where('name', $t['name'])->first();

                return $tag ? ['tag' => $tag, 'confidence' => $t['confidence']] : null;
            })
            ->filter()
            ->values();

        // Store raw AI response for reference
        $ticket->ai_suggested_tags = $result['tags'];
        $ticket->ai_processed_at = now();

        // Handle miscategorisation flag
        if ($config->flag_miscategorised && $result['flagged']) {
            $ticket->ai_flagged = true;
            $ticket->ai_flag_reason = $result['flag_reason'];
        }

        $ticket->save();

        // Apply or suggest tags
        if ($tagSuggestions->isNotEmpty()) {
            $syncData = [];

            foreach ($tagSuggestions as $suggestion) {
                $syncData[$suggestion['tag']->id] = [
                    'confidence' => $suggestion['confidence'],
                    'is_ai_suggested' => true,
                    'is_confirmed' => $config->auto_apply,
                ];
            }

            $ticket->tags()->syncWithoutDetaching($syncData);
        }
    }
}
