<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleListTool;

#[CoversClass(ArticleListTool::class)]
final class ArticleListToolTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleListTool $tool;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new ArticleListTool($this->articleRepository, $this->contentManager);
    }

    public function testListArticlesReturnsPaginatedResults(): void
    {
        $article1 = $this->createMock(ArticleInterface::class);
        $article1->method('getUuid')->willReturn('uuid-1');
        $article2 = $this->createMock(ArticleInterface::class);
        $article2->method('getUuid')->willReturn('uuid-2');

        $this->articleRepository->method('findBy')->willReturn([$article1, $article2]);
        $this->articleRepository->method('countBy')->willReturn(5);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test']);

        $result = $this->tool->listArticles('en');

        $this->assertCount(2, $result['articles']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['limit']);
        $this->assertSame('uuid-1', $result['articles'][0]['uuid']);
        $this->assertSame('uuid-2', $result['articles'][1]['uuid']);
    }

    public function testListArticlesAppliesTemplateFilter(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (array $filters): bool => isset($filters['templateKeys'])
                    && $filters['templateKeys'] === ['blog']),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([]);
        $this->articleRepository->method('countBy')->willReturn(0);

        $this->tool->listArticles('en', 'blog');
    }

    public function testListArticlesDefaultsPaginationToPage1Limit20(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (array $filters): bool => 1 === $filters['page'] && 20 === $filters['limit']),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([]);
        $this->articleRepository->method('countBy')->willReturn(0);

        $this->tool->listArticles('en');
    }

    public function testListArticlesResolvesAndNormalizesEachArticle(): void
    {
        $article1 = $this->createMock(ArticleInterface::class);
        $article1->method('getUuid')->willReturn('uuid-1');
        $article2 = $this->createMock(ArticleInterface::class);
        $article2->method('getUuid')->willReturn('uuid-2');
        $article3 = $this->createMock(ArticleInterface::class);
        $article3->method('getUuid')->willReturn('uuid-3');

        $this->articleRepository->method('findBy')->willReturn([$article1, $article2, $article3]);
        $this->articleRepository->method('countBy')->willReturn(3);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager
            ->expects($this->exactly(3))
            ->method('resolve')
            ->willReturn($dimensionContent);
        $this->contentManager
            ->expects($this->exactly(3))
            ->method('normalize')
            ->willReturn(['title' => 'Test']);

        $this->tool->listArticles('en');
    }

    public function testListArticlesMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleListTool::class, 'listArticles');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listArticles() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_article_list', $instance->name);
    }
}
