<?php

namespace App\Services\AiTagging;

use Illuminate\Support\Facades\Http;

class MistralProvider extends BaseProvider
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
            ->withToken($this->apiKey)
            ->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => $this->model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpdesk ticket classifier. Respond with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $response->throw();

        $text = $response->json('choices.0.message.content', '');

        return $this->parseResponse($text);
    }
}
