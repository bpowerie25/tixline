<?php

namespace App\Services\AiTagging;

interface AiTaggingProvider
{
    /**
     * Analyse a ticket and return tag suggestions + optional miscategorisation flag.
     *
     * @param  string  $subject  Ticket subject
     * @param  string  $body  Sanitized ticket body (anonymised)
     * @param  array<string>  $availableTags  List of tag names the AI can choose from
     * @param  array<string>  $availableTeams  List of team names for miscategorisation check
     * @param  string|null  $currentTeam  The team the ticket is currently assigned to
     * @return array{tags: array<array{name: string, confidence: float}>, flagged: bool, flag_reason: string|null}
     */
    public function analyse(
        string $subject,
        string $body,
        array $availableTags,
        array $availableTeams,
        ?string $currentTeam,
    ): array;
}
