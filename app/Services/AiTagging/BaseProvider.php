<?php

namespace App\Services\AiTagging;

use Illuminate\Support\Facades\Log;

abstract class BaseProvider implements AiTaggingProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
    ) {}

    protected function buildPrompt(
        string $subject,
        string $body,
        array $availableTags,
        array $availableTeams,
        ?string $currentTeam,
    ): string {
        $tagList = implode(', ', $availableTags);
        $teamList = implode(', ', $availableTeams);
        $teamContext = $currentTeam
            ? "The ticket is currently assigned to: {$currentTeam}"
            : 'The ticket is not assigned to any team.';

        return <<<PROMPT
You are a helpdesk ticket classifier. Analyse the following support ticket and:

1. Suggest which tags apply from the available list. Only suggest tags that are clearly relevant.
2. For each suggested tag, provide a confidence score between 0.0 and 1.0.
3. Assess whether the ticket appears to be assigned to the wrong team based on its content.

Available tags: {$tagList}
Available teams: {$teamList}
{$teamContext}

Ticket subject: {$subject}
Ticket body:
{$body}

Respond with valid JSON only, no markdown formatting. Use this exact structure:
{
  "tags": [
    {"name": "Tag Name", "confidence": 0.95}
  ],
  "flagged": false,
  "flag_reason": null
}

Rules:
- Only suggest tags from the available list (exact name match).
- If no tags apply, return an empty tags array.
- Set "flagged" to true only if the ticket content clearly does not match the assigned team.
- If flagged, explain briefly in "flag_reason" which team would be more appropriate and why.
- Do not invent tags not in the available list.
PROMPT;
    }

    protected function parseResponse(string $raw): array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));

        $data = json_decode($cleaned, true);

        if (! is_array($data) || ! isset($data['tags'])) {
            Log::warning('AI tagging: failed to parse response', ['raw' => $raw]);

            return ['tags' => [], 'flagged' => false, 'flag_reason' => null];
        }

        return [
            'tags' => array_map(fn ($t) => [
                'name' => $t['name'] ?? '',
                'confidence' => (float) ($t['confidence'] ?? 0),
            ], $data['tags'] ?? []),
            'flagged' => (bool) ($data['flagged'] ?? false),
            'flag_reason' => $data['flag_reason'] ?? null,
        ];
    }
}
