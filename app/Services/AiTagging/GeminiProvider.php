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

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
            ],
        ];

        // Try v1beta first (required for newer models), fall back to v1
        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            $payload
        );

        if ($response->status() === 404) {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1/models/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );
        }

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text', '');

        return $this->parseResponse($text);
    }
}
