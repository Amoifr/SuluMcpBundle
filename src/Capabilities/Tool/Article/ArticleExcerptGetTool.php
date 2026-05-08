<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Domain\Exception\ArticleNotFoundException;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;

class ArticleExcerptGetTool
{
    use ContentNormalizerTrait;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_excerpt_get',
        description: 'Read the excerpt (teaser) data for an article (title, description, more text, image, icon). Returns the draft state. Use sulu_article_excerpt_update to write.',
    )]
    public function getArticleExcerpt(string $uuid, string $locale): array
    {
        try {
            $article = $this->articleRepository->getOneBy(
                [
                    'uuid' => $uuid,
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

            $normalized = $this->contentManager->normalize($dimensionContent);

            return \array_merge(
                [
                    'uuid' => $article->getUuid(),
                    'locale' => $locale,
                ],
                $this->extractExcerpt($normalized),
            );
        } catch (ArticleNotFoundException) {
            return [
                'error' => 'Article not found: '.$uuid,
            ];
        }
    }
}
