<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly PageRepositoryInterface $pageRepository,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_update',
        description: 'Update an existing page. Reads the current page state, merges your changes, and writes back — so you only need to pass the fields you want to change. Pass template-specific field values in "content" as a flat object: content={"article": "<p>Updated HTML</p>"}. You can update title, url, and template as separate parameters. The page stays in draft state after updating — call sulu_page_publish to make changes live.',
    )]
    public function updatePage(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $url = null,
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"article": "<p>HTML content</p>"}', additionalProperties: true)]
        ?array $content = null,
    ): array {
        try {
            // Read current page state to get template and existing content
            $page = $this->pageRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            );

            $currentDimensionContent = $this->contentManager->resolve($page, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $currentData = $this->contentManager->normalize($currentDimensionContent);

            // Build update data: start with current state, overlay user changes
            $data = \array_merge(
                $currentData,
                ['locale' => $locale],
            );

            if (null !== $title) {
                $data['title'] = $title;
            }
            if (null !== $url) {
                $data['url'] = $url;
            }
            if (null !== $template) {
                $data['template'] = $template;
            }
            if (null !== $content) {
                $data = \array_merge($data, self::normalizeContent($content));
            }

            // Ensure all array keys are strings (Sulu's MetadataResolver requires string keys)
            $data = $this->stringifyKeys($data);

            $message = new ModifyPageMessage(['uuid' => $uuid], $data);

            /** @var PageInterface $updatedPage */
            $updatedPage = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($updatedPage, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            return [
                'success' => true,
                'uuid' => $updatedPage->getUuid(),
                'data' => $normalized,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_page_get) and content fields match the template schema (use sulu_get_context). Pass content as a flat object: content={"article": "<p>...</p>"}.',
            ];
        }
    }

    /**
     * Normalize content from AI clients that may send it as a list instead of a flat object.
     *
     * Handles: [{"article": "..."}] → ["article" => "..."]
     * Handles: [{"name": "article", "value": "..."}] → ["article" => "..."]
     * Passes through: {"article": "..."} → ["article" => "..."]
     *
     * @param array<mixed> $content
     *
     * @return array<string, mixed>
     */
    public static function normalizeContent(array $content): array
    {
        // Already a flat key-value map (associative array)
        if ([] !== $content && !\array_is_list($content)) {
            return $content;
        }

        // List of objects — merge each into a flat map
        $normalized = [];
        foreach ($content as $item) {
            if (\is_array($item)) {
                // Format: {"name": "field", "value": "..."} → ["field" => "..."]
                if (isset($item['name'], $item['value'])) {
                    $normalized[(string) $item['name']] = $item['value'];
                } else {
                    // Format: {"article": "..."} → merge directly
                    $normalized = \array_merge($normalized, $item);
                }
            }
        }

        return $normalized;
    }
}
