<?php

namespace Tests\Unit;

use App\Models\AiTaggingConfig;
use App\Models\Tag;
use App\Models\Team;
use App\Models\Ticket;
use App\Services\AiTagging\AiTagger;
use App\Services\AiTagging\AiTaggingProvider;
use App\Services\AiTagging\BaseProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTaggerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'subject' => 'Cannot login to my account',
            'body' => '<p>Hi, I cannot login. My email is john@example.com and my phone is 555-1234. Thanks, John Doe</p>',
            'requester_name' => 'John Doe',
            'requester_email' => 'john@example.com',
        ], $overrides));
    }

    protected function makeConfig(array $overrides = []): AiTaggingConfig
    {
        return AiTaggingConfig::create(array_merge([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key-123',
            'auto_apply' => false,
            'confidence_threshold' => 0.700,
            'flag_miscategorised' => true,
            'tag_on_create' => false,
        ], $overrides));
    }

    // --- Anonymisation tests ---

    public function test_anonymise_strips_email_addresses(): void
    {
        $ticket = $this->makeTicket(['body' => 'Contact me at user@domain.com please']);
        $tagger = new AiTagger;

        $method = new \ReflectionMethod($tagger, 'anonymise');
        $result = $method->invoke($tagger, $ticket);

        $this->assertStringNotContainsString('user@domain.com', $result);
        $this->assertStringContainsString('[EMAIL]', $result);
    }

    public function test_anonymise_strips_phone_numbers(): void
    {
        $ticket = $this->makeTicket(['body' => 'Call me at 555-123-4567']);
        $tagger = new AiTagger;

        $method = new \ReflectionMethod($tagger, 'anonymise');
        $result = $method->invoke($tagger, $ticket);

        $this->assertStringNotContainsString('555-123-4567', $result);
    }

    public function test_anonymise_replaces_requester_name(): void
    {
        $ticket = $this->makeTicket([
            'body' => 'Hi this is John Doe, please help',
            'requester_name' => 'John Doe',
        ]);
        $tagger = new AiTagger;

        $method = new \ReflectionMethod($tagger, 'anonymise');
        $result = $method->invoke($tagger, $ticket);

        $this->assertStringNotContainsString('John Doe', $result);
        $this->assertStringContainsString('[REQUESTER]', $result);
    }

    public function test_anonymise_truncates_very_long_bodies(): void
    {
        $ticket = $this->makeTicket(['body' => str_repeat('a', 5000)]);
        $tagger = new AiTagger;

        $method = new \ReflectionMethod($tagger, 'anonymise');
        $result = $method->invoke($tagger, $ticket);

        $this->assertStringContainsString('[TRUNCATED]', $result);
        $this->assertLessThanOrEqual(3020, mb_strlen($result)); // 3000 + [TRUNCATED] + newline
    }

    public function test_anonymise_handles_null_body(): void
    {
        $ticket = $this->makeTicket(['body' => null]);
        $tagger = new AiTagger;

        $method = new \ReflectionMethod($tagger, 'anonymise');
        $result = $method->invoke($tagger, $ticket);

        $this->assertIsString($result);
    }

    // --- Response parsing tests ---

    public function test_parse_response_extracts_tags(): void
    {
        $provider = new class('key', 'model') extends BaseProvider {
            public function analyse(string $subject, string $body, array $availableTags, array $availableTeams, ?string $currentTeam): array
            {
                return [];
            }

            public function testParse(string $raw): array
            {
                return $this->parseResponse($raw);
            }
        };

        $json = json_encode([
            'tags' => [
                ['name' => 'Billing', 'confidence' => 0.95],
                ['name' => 'Account', 'confidence' => 0.72],
            ],
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $result = $provider->testParse($json);

        $this->assertCount(2, $result['tags']);
        $this->assertEquals('Billing', $result['tags'][0]['name']);
        $this->assertEquals(0.95, $result['tags'][0]['confidence']);
        $this->assertFalse($result['flagged']);
    }

    public function test_parse_response_handles_markdown_code_fences(): void
    {
        $provider = new class('key', 'model') extends BaseProvider {
            public function analyse(string $subject, string $body, array $availableTags, array $availableTeams, ?string $currentTeam): array
            {
                return [];
            }

            public function testParse(string $raw): array
            {
                return $this->parseResponse($raw);
            }
        };

        $json = "```json\n" . json_encode([
            'tags' => [['name' => 'Login', 'confidence' => 0.88]],
            'flagged' => true,
            'flag_reason' => 'Should be in IT Support',
        ]) . "\n```";

        $result = $provider->testParse($json);

        $this->assertCount(1, $result['tags']);
        $this->assertTrue($result['flagged']);
        $this->assertEquals('Should be in IT Support', $result['flag_reason']);
    }

    public function test_parse_response_returns_empty_on_invalid_json(): void
    {
        $provider = new class('key', 'model') extends BaseProvider {
            public function analyse(string $subject, string $body, array $availableTags, array $availableTeams, ?string $currentTeam): array
            {
                return [];
            }

            public function testParse(string $raw): array
            {
                return $this->parseResponse($raw);
            }
        };

        $result = $provider->testParse('not valid json at all');

        $this->assertEmpty($result['tags']);
        $this->assertFalse($result['flagged']);
    }

    // --- Tag application tests ---

    public function test_applies_tags_above_confidence_threshold(): void
    {
        $this->makeConfig(['confidence_threshold' => 0.800]);

        $highTag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $lowTag = Tag::create(['name' => 'Login', 'slug' => 'login']);
        Tag::create(['name' => 'Other', 'slug' => 'other']);

        $ticket = $this->makeTicket();

        // Mock the provider
        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [
                ['name' => 'Billing', 'confidence' => 0.95],
                ['name' => 'Login', 'confidence' => 0.60], // below threshold
            ],
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $result = $tagger->tagTicket($ticket);

        $this->assertTrue($result);
        $ticket->refresh();

        // Only Billing should be attached (above 0.8 threshold)
        $this->assertEquals(1, $ticket->tags()->count());
        $this->assertTrue($ticket->tags->contains($highTag));
        $this->assertFalse($ticket->tags->contains($lowTag));
    }

    public function test_sets_flagged_when_miscategorised(): void
    {
        $this->makeConfig(['flag_miscategorised' => true]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $team = Team::create(['name' => 'IT Support', 'slug' => 'it-support']);
        $ticket = $this->makeTicket(['team_id' => $team->id]);

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [['name' => 'Billing', 'confidence' => 0.90]],
            'flagged' => true,
            'flag_reason' => 'This appears to be a billing issue, not IT support.',
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $ticket->refresh();
        $this->assertTrue($ticket->ai_flagged);
        $this->assertEquals('This appears to be a billing issue, not IT support.', $ticket->ai_flag_reason);
        $this->assertNotNull($ticket->ai_processed_at);
    }

    public function test_does_not_flag_when_flag_miscategorised_is_off(): void
    {
        $this->makeConfig(['flag_miscategorised' => false]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [],
            'flagged' => true,
            'flag_reason' => 'Wrong team',
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $ticket->refresh();
        $this->assertFalse($ticket->ai_flagged);
    }

    public function test_auto_apply_sets_confirmed_flag(): void
    {
        $this->makeConfig(['auto_apply' => true, 'confidence_threshold' => 0.500]);

        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [['name' => 'Billing', 'confidence' => 0.90]],
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $pivot = $ticket->tags()->where('tag_id', $tag->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->is_confirmed);
    }

    public function test_suggest_only_does_not_confirm(): void
    {
        $this->makeConfig(['auto_apply' => false, 'confidence_threshold' => 0.500]);

        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [['name' => 'Billing', 'confidence' => 0.90]],
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $pivot = $ticket->tags()->where('tag_id', $tag->id)->first()->pivot;
        $this->assertFalse((bool) $pivot->is_confirmed);
    }

    public function test_returns_false_when_not_configured(): void
    {
        // No AiTaggingConfig exists
        $ticket = $this->makeTicket();
        $tagger = new AiTagger;

        $this->assertFalse($tagger->tagTicket($ticket));
    }

    public function test_returns_false_when_is_enabled_is_false(): void
    {
        $this->makeConfig(['is_enabled' => false]);
        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();
        $tagger = new AiTagger;

        $this->assertFalse($tagger->tagTicket($ticket));
        $this->assertNull($ticket->fresh()->ai_processed_at);
    }

    public function test_returns_false_when_no_tags_defined(): void
    {
        $this->makeConfig();
        // No tags created
        $ticket = $this->makeTicket();
        $tagger = new AiTagger;

        $this->assertFalse($tagger->tagTicket($ticket));
    }

    public function test_returns_false_on_provider_exception(): void
    {
        $this->makeConfig();
        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willThrowException(new \RuntimeException('API error'));

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $this->assertFalse($tagger->tagTicket($ticket));
    }

    public function test_stores_raw_ai_response(): void
    {
        $this->makeConfig(['confidence_threshold' => 0.500]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $aiTags = [['name' => 'Billing', 'confidence' => 0.90]];

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => $aiTags,
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $ticket->refresh();
        $this->assertEquals($aiTags, $ticket->ai_suggested_tags);
    }

    public function test_ignores_tags_not_in_database(): void
    {
        $this->makeConfig(['confidence_threshold' => 0.500]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();

        $mockProvider = $this->createMock(AiTaggingProvider::class);
        $mockProvider->method('analyse')->willReturn([
            'tags' => [
                ['name' => 'Billing', 'confidence' => 0.90],
                ['name' => 'Nonexistent Tag', 'confidence' => 0.85],
            ],
            'flagged' => false,
            'flag_reason' => null,
        ]);

        $tagger = $this->getMockBuilder(AiTagger::class)
            ->onlyMethods(['resolveProvider'])
            ->getMock();
        $tagger->method('resolveProvider')->willReturn($mockProvider);

        $tagger->tagTicket($ticket);

        $ticket->refresh();
        $this->assertEquals(1, $ticket->tags()->count());
    }
}
