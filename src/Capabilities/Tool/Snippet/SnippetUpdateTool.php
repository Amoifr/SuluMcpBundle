<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Snippet;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Snippet\Application\Message\ModifySnippetMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class SnippetUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly BlockDataValidator $blockDataValidator,
        private readonly BlockIdGeneratorInterface $blockIdGenerator,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_snippet_update',
        description: 'Update an existing snippet. Reads the current snippet state, merges your changes, and writes back — so you only need to pass the fields you want to change. Pass template-specific field values in "content" as a flat object: content={"body": "<p>Updated HTML</p>"}. Content may also include a full "blocks" tree (nested blocks allowed) to replace the block content in one call — block _ids are assigned automatically and unknown block fields are rejected before saving. You can update title and template as separate parameters. The snippet stays in draft state after updating — call sulu_content_publish (type: snippet) to make changes live.',
    )]
    public function updateSnippet(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"body": "<p>HTML content</p>"}', additionalProperties: true)]
        ?array $content = null,
    ): array {
        try {
            $snippet = $this->contentTypeResolver->loadDraft('snippet', $uuid, $locale);

            if (null === $snippet) {
                return ['error' => \sprintf('Snippet not found: %s', $uuid)];
            }

            $currentDimensionContent = $this->contentManager->resolve($snippet, [ // @phpstan-ignore argument.templateType
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $currentData = $this->contentManager->normalize($currentDimensionContent);

            $data = \array_merge(
                $currentData,
                ['locale' => $locale],
            );

            if (null !== $title) {
                $data['title'] = $title;
            }
            if (null !== $template) {
                $data['template'] = $template;
            }
            if (null !== $content) {
                $normalizedContent = self::normalizeContent($content);
                $templateKey = isset($data['template']) && \is_string($data['template']) ? $data['template'] : null;
                if ($validationError = $this->blockDataValidator->validateContentTree($normalizedContent, 'snippet', $templateKey)) {
                    return $validationError;
                }
                $normalizedContent = $this->assignBlockIds($normalizedContent, $this->blockIdGenerator);
                $data = \array_merge($data, $normalizedContent);
            }

            $data = $this->stringifyKeys($data);

            $message = new ModifySnippetMessage(['uuid' => $uuid], $data);

            /** @var SnippetInterface $updatedSnippet */
            $updatedSnippet = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($updatedSnippet, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            return [
                'success' => true,
                'uuid' => $updatedSnippet->getUuid(),
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update snippet %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_snippet_get) and content fields match the template schema (use sulu_get_context). Pass content as a flat object: content={"body": "<p>...</p>"}.',
            ];
        }
    }
}
