<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class BlockReorderTool
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param list<int> $newOrder
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_block_reorder',
        description: 'Reorder blocks on a page by specifying the new order of indices. newOrder must be an array containing every current index exactly once in the desired order. Example: if a page has 3 blocks (indices 0,1,2), passing newOrder=[2,0,1] moves the third block to first position. Call sulu_page_get first to see the current block order. The page must be re-published after reordering.',
    )]
    public function reorderBlocks(
        string $pageUuid,
        string $locale,
        string $blockProperty,
        array $newOrder,
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

            if (\count($newOrder) !== \count($blocks)) {
                return [
                    'error' => \sprintf(
                        'newOrder length (%d) does not match block count (%d).',
                        \count($newOrder),
                        \count($blocks),
                    ),
                ];
            }

            $sorted = $newOrder;
            \sort($sorted);
            if ($sorted !== \range(0, \count($blocks) - 1)) {
                return [
                    'error' => 'newOrder must contain each index from 0 to '
                        .(\count($blocks) - 1)
                        .' exactly once. Got: ['.\implode(', ', $newOrder).']',
                ];
            }

            $reordered = \array_map(
                static fn (int $i): array => $blocks[$i],
                $newOrder,
            );

            $data = [
                'locale' => $locale,
                'template' => $currentData['template'] ?? null,
                'title' => $currentData['title'] ?? null,
                $blockProperty => $reordered,
            ];

            $message = new ModifyPageMessage(['uuid' => $pageUuid], $data);

            /** @var PageInterface $updatedPage */
            $updatedPage = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $updatedPage->getUuid(),
                'blockCount' => \count($reordered),
                'order' => $newOrder,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to reorder blocks on page %s: %s', $pageUuid, $e->getMessage()),
                'hint' => 'Use sulu_page_get to see current blocks. newOrder must contain every index exactly once.',
            ];
        }
    }
}
