<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

class GuidelineGeneratorPrompt
{
    /**
     * @return array<int, array<string, mixed>>
     */
    #[McpPrompt(
        name: 'sulu_generate_guidelines',
        description: 'Guides the AI through analyzing existing pages in a webspace to generate content guidelines. The AI will read pages, analyze their tone, style, and audience, then save guidelines using sulu_update_guidelines.',
    )]
    public function generateGuidelines(string $webspace, string $locale): array
    {
        $promptText = <<<PROMPT
            You are generating a content writing brief for the "{$webspace}" webspace (locale: {$locale}).

            The goal: produce guidelines detailed enough that any writer — human or AI — could create new pages indistinguishable from the existing ones without ever seeing the originals.

            ## Step 1 — Read all existing content

            Call `sulu_page_list` with webspace="{$webspace}" and locale="{$locale}".
            Then call `sulu_page_get` for every page returned (or at least 5-10 covering different templates). Read the full content of each page. You need real text to analyze, not just metadata.

            ## Step 2 — Analyze the content

            Base your analysis strictly on the page content you just read. Do not use assumptions or general knowledge about the company. Extract the following:

            **Tone**: Describe the exact voice. Is it formal, conversational, technical, enthusiastic, restrained? Does it use humor? Is it first-person ("we"), second-person ("you"), or third-person? Does it address the reader directly? Give a concrete example sentence from the content that captures the tone.

            **Audience**: Who is being spoken to? What level of expertise is assumed? What problems or goals does the content assume the reader has? Are there jargon terms used without explanation (signals expert audience) or are concepts broken down (signals general audience)?

            **Style**: How long are sentences on average — short and punchy or long and detailed? How are paragraphs structured? Are headings used, and if so what style (questions, statements, action-oriented)? Are bullet lists common? Are there calls to action, and how are they phrased? Is content dense or spacious?

            **Vocabulary and terminology**: What specific words and phrases recur? How does the brand refer to itself, its products, its users? Are there terms that are always used (or always avoided)? List the key terms.

            **Content structure**: How are pages typically organized? What comes first — a hook, a statement, a question? How do pages end — with a CTA, a summary, contact info? What is the typical content length per page?

            **Formatting patterns**: How are blocks used — which block types appear most? Are images used inline or as heroes? Are quotes used? Code examples? Tables?

            ## Step 3 — Ask about prior knowledge

            Present your analysis summary to the user, then ask:
            "Should I also incorporate what I already know about your company from our conversation, or keep the guidelines based purely on what I found in the existing pages?"

            Wait for the user's answer before proceeding.

            ## Step 4 — Write and save guidelines

            Distill your analysis into actionable guidelines. Each field should be specific enough that a writer can follow it without seeing the original content.

            Call `sulu_update_guidelines` with webspace="{$webspace}" and these fields:

            - **tone**: The voice to write in. Include person (we/you), register (formal/casual), and attitude. Not just "professional" — describe HOW to be professional.
            - **audience**: Who the reader is, what they know, what they need. Specific enough to shape word choice.
            - **style**: Concrete rules: sentence length, paragraph length, heading style, use of lists, CTAs. Describe the structure a typical page should follow.
            - **brandRules**: Mandatory terminology, how to refer to the brand/products, words to always use and words to never use.
            - **dos**: Specific patterns to follow, with examples from the content. "Lead with the benefit" is better than "be clear".
            - **donts**: Specific patterns to avoid. "Never use passive voice in headings" is better than "don't be vague".

            Keep each field under 300 characters so the total stays within the 2000-character limit. Be specific over comprehensive — a few precise rules beat many vague ones.
            PROMPT;

        return [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $promptText],
                ],
            ],
        ];
    }
}
