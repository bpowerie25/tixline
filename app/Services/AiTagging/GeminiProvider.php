<?php

namespace App\Services\AiTagging;

use Illuminate\Support\Facades\Http;

class GeminiProvider extends BaseProvider
{
    public function analyse(
        string $subject,
        string $body,
        array $availableTags,
        array $availableTeams,
        ?string $currentTeam,
    ): array {
        $prompt = $this->buildPrompt($subject, $body, $availableTags, $availableTeams, $currentTeam);

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.2,
                ],
            ]
        );

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text', '');

        return $this->parseResponse($text);
    }
}
