<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Prompt;

use Mcp\Capability\Attribute\McpPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Prompt\GuidelineGeneratorPrompt;

#[CoversClass(GuidelineGeneratorPrompt::class)]
final class GuidelineGeneratorPromptTest extends TestCase
{
    private GuidelineGeneratorPrompt $prompt;

    protected function setUp(): void
    {
        $this->prompt = new GuidelineGeneratorPrompt();
    }

    public function testGenerateGuidelinesReturnsPromptMessages(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'en');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testGenerateGuidelinesMessageHasUserRole(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'en');

        $this->assertSame('user', $result[0]['role']);
    }

    public function testGenerateGuidelinesMessageHasTextContent(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'en');

        $this->assertSame('text', $result[0]['content'][0]['type']);
        $this->assertIsString($result[0]['content'][0]['text']);
    }

    public function testGenerateGuidelinesContainsWebspaceInPrompt(): void
    {
        $result = $this->prompt->generateGuidelines('mysite', 'en');

        $promptText = $result[0]['content'][0]['text'];
        $this->assertStringContainsString('mysite', $promptText);
    }

    public function testGenerateGuidelinesContainsLocaleInPrompt(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'de');

        $promptText = $result[0]['content'][0]['text'];
        $this->assertStringContainsString('de', $promptText);
    }

    public function testGenerateGuidelinesReferencesRequiredTools(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'en');

        $promptText = $result[0]['content'][0]['text'];
        $this->assertStringContainsString('sulu_page_list', $promptText);
        $this->assertStringContainsString('sulu_page_get', $promptText);
        $this->assertStringContainsString('sulu_update_guidelines', $promptText);
    }

    public function testGenerateGuidelinesInstructsAnalysisSteps(): void
    {
        $result = $this->prompt->generateGuidelines('example', 'en');

        $promptText = $result[0]['content'][0]['text'];
        $this->assertStringContainsString('Tone', $promptText);
        $this->assertStringContainsString('Audience', $promptText);
        $this->assertStringContainsString('Style', $promptText);
    }

    public function testGenerateGuidelinesMethodHasMcpPromptAttribute(): void
    {
        $reflection = new \ReflectionMethod(GuidelineGeneratorPrompt::class, 'generateGuidelines');
        $attributes = $reflection->getAttributes(McpPrompt::class);

        $this->assertCount(1, $attributes, 'generateGuidelines() must have exactly one #[McpPrompt] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_generate_guidelines', $instance->name);
    }
}
