<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
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

class BlockRemoveTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_block_remove',
        description: 'Remove a block from a page by its 0-based index. Call sulu_page_get first to see the current blocks array and identify which index to remove. The blockProperty must match the template property name that holds blocks (same as used in sulu_block_add). Remaining blocks shift down to fill the gap. The page must be re-published after removing blocks.',
    )]
    public function removeBlock(
        string $pageUuid,
        string $locale,
        string $blockProperty,
        int $blockIndex,
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

            if ($blockIndex < 0 || $blockIndex >= \count($blocks)) {
                return [
                    'error' => \sprintf(
                        'Block index %d out of range. Page has %d block(s) (valid indices: 0-%d).',
                        $blockIndex,
                        \count($blocks),
                        \max(0, \count($blocks) - 1),
                    ),
                ];
            }

            \array_splice($blocks, $blockIndex, 1);

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
                'removedIndex' => $blockIndex,
                'blockCount' => \count($blocks),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to remove block %d from page %s: %s', $blockIndex, $pageUuid, $e->getMessage()),
                'hint' => 'Use sulu_page_get to see current blocks and their indices before removing.',
            ];
        }
    }
}
