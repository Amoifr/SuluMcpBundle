<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleBlockReorderTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ArticleRepositoryInterface $articleRepository,
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
        name: 'sulu_article_block_reorder',
        description: 'Reorder blocks on an article by specifying the new order of indices. newOrder must be an array containing every current index exactly once in the desired order. Example: if an article has 3 blocks (indices 0,1,2), passing newOrder=[2,0,1] moves the third block to first position. Call sulu_article_get first to see the current block order. The article must be re-published after reordering.',
    )]
    public function reorderBlocks(
        string $articleUuid,
        string $locale,
        string $blockProperty,
        array $newOrder,
    ): array {
        try {
            $article = $this->articleRepository->getOneBy(
                [
                    'uuid' => $articleUuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true,
                ],
            );

            $dimensionContent = $this->contentManager->resolve($article, [
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

            // Ensure all array keys are strings (Sulu's MetadataResolver requires string keys)
            $data = $this->stringifyKeys($data);

            $message = new ModifyArticleMessage(['uuid' => $articleUuid], $data);

            /** @var ArticleInterface $updatedArticle */
            $updatedArticle = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $updatedArticle->getUuid(),
                'blockCount' => \count($reordered),
                'order' => $newOrder,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to reorder blocks on article %s: %s', $articleUuid, $e->getMessage()),
                'hint' => 'Use sulu_article_get to see current blocks. newOrder must contain every index exactly once.',
            ];
        }
    }
}
