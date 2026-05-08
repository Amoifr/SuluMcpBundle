<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleBlockAddTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly BlockIdGeneratorInterface $blockIdGenerator,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed> $blockData
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_block_add',
        description: 'Add a content block to an article. Blocks are typed components (e.g. "text", "image", "quote") defined by the project. Workflow: 1) Call sulu_get_context to see available block types and their fields. 2) Find the block property name in the template (e.g. "blocks" or "content"). 3) Pass blockType, blockProperty, and blockData with field values. The block is appended at the end or inserted at "position" (0-based). To add a block inside a parent block (nested blocks), pass parentBlockId with the _id of the parent block. The article must be re-published after adding blocks.',
    )]
    public function addBlock(
        string $articleUuid,
        string $locale,
        string $blockType,
        string $blockProperty,
        array $blockData = [],
        ?int $position = null,
        ?string $parentBlockId = null,
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

            // Normalize blockData: [{"content": "..."}] -> {"content": "..."} or pass through if already flat
            $blockData = $this->normalizeBlockData($blockData);

            $newBlock = $this->assignBlockIds(\array_merge(['type' => $blockType], $blockData), $this->blockIdGenerator);

            if (null !== $parentBlockId) {
                // Nested insert: find the parent block and add inside it
                $parentPath = $this->findBlockPath($currentData, $parentBlockId);
                if (null === $parentPath) {
                    return [
                        'error' => \sprintf('Parent block with _id "%s" not found in article %s.', $parentBlockId, $articleUuid),
                        'hint' => 'Use sulu_article_get to see block summaries with _id values.',
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

            $message = new ModifyArticleMessage(['uuid' => $articleUuid], $data);

            /** @var ArticleInterface $updatedArticle */
            $updatedArticle = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $updatedArticle->getUuid(),
                'blockCount' => \count($blocks),
                'addedAt' => $addedAt,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to add %s block to article %s: %s', $blockType, $articleUuid, $e->getMessage()),
                'hint' => 'Verify the article UUID exists (use sulu_article_get), the blockProperty matches a block field in the template, and blockType is a valid block type (use sulu_get_context to see available types).',
            ];
        }
    }
}
