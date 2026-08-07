<?php

namespace App\Services;

use App\Models\SpamCorpus;
use App\Models\Ticket;

class SpamLearner
{
    // Common words to ignore when learning
    protected array $stopWords = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for',
        'on', 'with', 'at', 'by', 'from', 'as', 'into', 'about', 'between',
        'through', 'after', 'before', 'above', 'below', 'up', 'down', 'out',
        'off', 'over', 'under', 'again', 'then', 'once', 'here', 'there',
        'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few',
        'more', 'most', 'other', 'some', 'such', 'no', 'not', 'only', 'own',
        'same', 'so', 'than', 'too', 'very', 'just', 'because', 'but', 'and',
        'or', 'if', 'while', 'that', 'this', 'what', 'which', 'who', 'whom',
        'its', 'his', 'her', 'their', 'our', 'your', 'my', 'me', 'him', 'her',
        'us', 'them', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
        'dear', 'hi', 'hello', 'please', 'thank', 'thanks', 'regards',
        're', 'fw', 'fwd', 'subject', 'sent', 'received',
    ];

    /**
     * Learn from a ticket that was marked as spam.
     */
    public function learnFromTicket(Ticket $ticket): void
    {
        // Learn sender email and domain
        $email = strtolower($ticket->requester_email);
        $domain = substr($email, strrpos($email, '@') + 1);

        $this->record('email', $email);
        $this->record('domain', $domain);

        // Learn keywords from subject
        $subjectWords = $this->extractKeywords($ticket->subject);
        foreach ($subjectWords as $word) {
            $this->record('keyword', $word);
        }

        // Learn keywords from body (strip HTML first)
        $bodyText = strip_tags($ticket->body ?? '');
        $bodyWords = $this->extractKeywords($bodyText);
        foreach ($bodyWords as $word) {
            $this->record('keyword', $word);
        }

        // Learn 2-word phrases from subject
        $phrases = $this->extractPhrases($ticket->subject);
        foreach ($phrases as $phrase) {
            $this->record('phrase', $phrase);
        }
    }

    /**
     * Score an incoming email against the learned corpus.
     * Returns a score from 0 (clean) upward (spammy).
     */
    public function score(string $fromEmail, string $subject, string $body): float
    {
        $score = 0.0;

        $email = strtolower($fromEmail);
        $domain = substr($email, strrpos($email, '@') + 1);

        // Exact email match — strong signal
        $emailHits = SpamCorpus::where('type', 'email')->where('value', $email)->value('hits');
        if ($emailHits) {
            $score += min($emailHits * 3.0, 15.0);
        }

        // Domain match — moderate signal
        $domainHits = SpamCorpus::where('type', 'domain')->where('value', $domain)->value('hits');
        if ($domainHits) {
            $score += min($domainHits * 1.5, 10.0);
        }

        // Keyword matches in subject — each learned keyword adds weight
        $subjectWords = $this->extractKeywords($subject);
        if (! empty($subjectWords)) {
            $keywordMatches = SpamCorpus::where('type', 'keyword')
                ->whereIn('value', $subjectWords)
                ->get();

            foreach ($keywordMatches as $match) {
                // Words seen more often in spam get higher weight, capped
                $score += min($match->hits * 0.5, 3.0);
            }
        }

        // Phrase matches in subject — stronger signal than individual words
        $phrases = $this->extractPhrases($subject);
        if (! empty($phrases)) {
            $phraseMatches = SpamCorpus::where('type', 'phrase')
                ->whereIn('value', $phrases)
                ->get();

            foreach ($phraseMatches as $match) {
                $score += min($match->hits * 1.0, 5.0);
            }
        }

        // Body keyword matches (lighter weight since bodies are longer)
        $bodyText = strip_tags($body);
        $bodyWords = $this->extractKeywords($bodyText);
        if (! empty($bodyWords)) {
            $bodyMatches = SpamCorpus::where('type', 'keyword')
                ->whereIn('value', array_slice($bodyWords, 0, 50))
                ->sum('hits');

            $score += min($bodyMatches * 0.2, 5.0);
        }

        return round($score, 2);
    }

    protected function record(string $type, string $value): void
    {
        $entry = SpamCorpus::where('type', $type)->where('value', $value)->first();

        if ($entry) {
            $entry->increment('hits');
        } else {
            SpamCorpus::create(['type' => $type, 'value' => $value, 'hits' => 1]);
        }
    }

    protected function extractKeywords(string $text): array
    {
        $text = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text));
        $words = array_filter(explode(' ', $text), fn ($w) => strlen($w) >= 3);
        $words = array_diff($words, $this->stopWords);

        return array_values(array_unique($words));
    }

    protected function extractPhrases(string $text): array
    {
        $text = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text));
        $words = array_filter(explode(' ', $text), fn ($w) => strlen($w) >= 2);
        $words = array_values($words);

        $phrases = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrase = $words[$i] . ' ' . $words[$i + 1];
            // Skip phrases made entirely of stop words
            if (! in_array($words[$i], $this->stopWords) || ! in_array($words[$i + 1], $this->stopWords)) {
                $phrases[] = $phrase;
            }
        }

        return array_unique($phrases);
    }
}
