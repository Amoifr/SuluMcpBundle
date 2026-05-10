<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class BlockAddTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly BlockIdGeneratorInterface $blockIdGenerator,
        private readonly BlockDataValidator $blockDataValidator,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed> $blockData
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_block_add',
        description: 'Add a content block to a page. Blocks are typed components (e.g. "text", "image", "quote") defined by the project. Workflow: 1) Call sulu_get_context to see available block types and their fields. 2) Find the block property name in the template (e.g. "blocks" or "content"). 3) Pass blockType, blockProperty, and blockData as a flat object mapping the block-type\'s template field names to values, e.g. blockData={"title": "Heading", "description": "<p>Body</p>"}. Unknown keys are rejected against the template schema; the internal {name, value} storage shape is rejected too. The block is appended or inserted at `position` (0-based). To add a block inside another, pass parentBlockId with the parent\'s _id. The page must be re-published after adding blocks.',
    )]
    public function addBlock(
        string $pageUuid,
        string $locale,
        string $blockType,
        string $blockProperty,
        array $blockData = [],
        ?int $position = null,
        ?string $parentBlockId = null,
    ): array {
        try {
            $page = $this->pageRepository->getOneBy(
                [
                    'uuid' => $pageUuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            );

            $dimensionContent = $this->contentManager->resolve($page, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $currentData = $this->contentManager->normalize($dimensionContent);

            /** @var list<array<string, mixed>> $blocks */
            $blocks = $currentData[$blockProperty] ?? [];

            // Normalize blockData: [{"content": "..."}] -> {"content": "..."} or pass through if already flat
            $blockData = $this->normalizeBlockData($blockData);

            $templateKey = isset($currentData['template']) && \is_string($currentData['template'])
                ? $currentData['template']
                : null;
            if ($validationError = $this->blockDataValidator->validate('page', $templateKey, $blockType, $blockData)) {
                return $validationError;
            }

            $newBlock = $this->assignBlockIds(\array_merge(['type' => $blockType], $blockData), $this->blockIdGenerator);

            if (null !== $parentBlockId) {
                // Nested insert: find the parent block and add inside it
                $parentPath = $this->findBlockPath($currentData, $parentBlockId);
                if (null === $parentPath) {
                    return [
                        'error' => \sprintf('Parent block with _id "%s" not found in page %s.', $parentBlockId, $pageUuid),
                        'hint' => 'Use sulu_page_get to see block summaries with _id values.',
                    ];
                }
                $result = $this->insertBlockAtPath($blocks, $parentPath['indices'], $newBlock, $position);
                if (null === $result) {
                    return ['error' => \sprintf('Could not insert block into parent "%s" — no nested block list found.', $parentBlockId)];
                }
                $blocks = $result['blocks'];
                $addedAt = $result['addedAt'];
            } elseif (null !== $position && $position >= 0 && $position <= \count($blocks)) {
                \array_splice($blocks, $position, 0, [$newBlock]);
                $addedAt = $position;
            } else {
                $blocks[] = $newBlock;
                $addedAt = \count($blocks) - 1;
            }

            $data = [
                'locale' => $locale,
                'template' => $currentData['template'] ?? null,
                'title' => $currentData['title'] ?? null,
                $blockProperty => $blocks,
            ];

            // Ensure all array keys are strings (Sulu's MetadataResolver requires string keys)
            $data = $this->stringifyKeys($data);

            $message = new ModifyPageMessage(['uuid' => $pageUuid], $data);

            /** @var PageInterface $updatedPage */
            $updatedPage = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $updatedPage->getUuid(),
                'blockCount' => \count($blocks),
                'addedAt' => $addedAt,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to add %s block to page %s: %s', $blockType, $pageUuid, $e->getMessage()),
                'hint' => 'Verify the page UUID exists (use sulu_page_get), the blockProperty matches a block field in the template, and blockType is a valid block type (use sulu_get_context to see available types).',
            ];
        }
    }
}
