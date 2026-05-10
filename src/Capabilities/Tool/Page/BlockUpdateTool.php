<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class BlockUpdateTool
{
    use HandleTrait;
    use ContentNormalizerTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
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
        name: 'sulu_block_update',
        description: 'Update a single block by its _id. Pass blockData as a flat object mapping the block-type\'s template field names to new values, e.g. blockData={"title": "New heading"}. Only the keys you pass are changed; other fields are preserved. Unknown keys are rejected against the block type\'s schema; the internal {name, value} storage shape is rejected too. Use sulu_page_get or sulu_article_get to find block _id values (returned in block summaries), and sulu_block_list to read full content before updating. The entity must be re-published after updating blocks.',
    )]
    public function updateBlock(
        string $type,
        string $uuid,
        string $locale,
        string $blockId,
        array $blockData,
    ): array {
        try {
            $entity = $this->loadEntity($type, $uuid, $locale);

            if (null === $entity) {
                return ['error' => \sprintf('%s not found: %s', \ucfirst($type), $uuid)];
            }

            $dimensionContent = $this->contentManager->resolve($entity, [ // @phpstan-ignore argument.templateType
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $currentData = $this->contentManager->normalize($dimensionContent);

            // Find the block by _id anywhere in the block tree (including nested blocks)
            $found = $this->findBlockPath($currentData, $blockId);

            if (null === $found) {
                return [
                    'error' => \sprintf('Block with _id "%s" not found in %s %s.', $blockId, $type, $uuid),
                    'hint' => 'Use sulu_page_get or sulu_article_get to see block summaries with _id values.',
                ];
            }

            $foundProperty = $found['property'];
            $foundIndices = $found['indices'];

            // Merge new data over existing block (partial update)
            $blockData = $this->normalizeBlockData($blockData);

            $existingBlock = $this->getBlockAtPath($currentData[$foundProperty], $foundIndices);
            $blockType = isset($existingBlock['type']) && \is_string($existingBlock['type'])
                ? $existingBlock['type']
                : null;
            $templateKey = isset($currentData['template']) && \is_string($currentData['template'])
                ? $currentData['template']
                : null;
            if (null !== $blockType && $validationError = $this->blockDataValidator->validate($type, $templateKey, $blockType, $blockData)) {
                return $validationError;
            }

            $blockData = $this->assignBlockIds($blockData, $this->blockIdGenerator);

            /** @var list<array<string, mixed>> $topLevelBlocks */
            $topLevelBlocks = $currentData[$foundProperty];
            $currentData[$foundProperty] = $this->setBlockAtPath($topLevelBlocks, $foundIndices, $blockData);

            $data = [
                'locale' => $locale,
                'template' => $currentData['template'] ?? null,
                'title' => $currentData['title'] ?? null,
                $foundProperty => $currentData[$foundProperty],
            ];

            $data = $this->stringifyKeys($data);

            $message = match ($type) {
                'page' => new ModifyPageMessage(['uuid' => $uuid], $data),
                'article' => new ModifyArticleMessage(['uuid' => $uuid], $data),
            };

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'blockId' => $blockId,
                'blockProperty' => $foundProperty,
                'blockPath' => $foundIndices,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update block "%s" in %s %s: %s', $blockId, $type, $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists and the block _id is correct (use sulu_page_get or sulu_article_get to check).',
            ];
        }
    }

    private function loadEntity(string $type, string $uuid, string $locale): ?object
    {
        $filters = [
            'uuid' => $uuid,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        try {
            return match ($type) {
                'page' => $this->pageRepository->getOneBy($filters, [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true]),
                'article' => $this->articleRepository->getOneBy($filters, [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true]),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
