<?php

namespace App\Services\AiTagging;

use Illuminate\Support\Facades\Http;

class ClaudeProvider extends BaseProvider
{
    public function analyse(
        string $subject,
        string $body,
        array $availableTags,
        array $availableTeams,
        ?string $currentTeam,
    ): array {
        $prompt = $this->buildPrompt($subject, $body, $availableTags, $availableTeams, $currentTeam);

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $response->throw();

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return $this->parseResponse($text);
    }
}
