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

class ArticleBlockRemoveTool
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
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_block_remove',
        description: 'Remove a block from an article by its 0-based index. Call sulu_article_get first to see the current blocks array and identify which index to remove. The blockProperty must match the template property name that holds blocks. Remaining blocks shift down to fill the gap. The article must be re-published after removing blocks.',
    )]
    public function removeBlock(
        string $articleUuid,
        string $locale,
        string $blockProperty,
        int $blockIndex,
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

            if ($blockIndex < 0 || $blockIndex >= \count($blocks)) {
                return [
                    'error' => \sprintf(
                        'Block index %d out of range. Article has %d block(s) (valid indices: 0-%d).',
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

            $message = new ModifyArticleMessage(['uuid' => $articleUuid], $data);

            /** @var ArticleInterface $updatedArticle */
            $updatedArticle = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $updatedArticle->getUuid(),
                'removedIndex' => $blockIndex,
                'blockCount' => \count($blocks),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to remove block %d from article %s: %s', $blockIndex, $articleUuid, $e->getMessage()),
                'hint' => 'Use sulu_article_get to see current blocks and their indices before removing.',
            ];
        }
    }
}
