<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleCreateTool;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\ContentMetadataMapper;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleCreateTool::class)]
final class ArticleCreateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private BlockIdGeneratorInterface&MockObject $blockIdGenerator;
    private MetadataProviderInterface&MockObject $formMetadataProvider;
    private MetadataProviderInterface&MockObject $mapperMetadataProvider;
    private ArticleCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->blockIdGenerator = $this->createMock(BlockIdGeneratorInterface::class);
        $this->blockIdGenerator->method('generateId')->willReturn('gen-id');
        $this->formMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        // Default: provider returns a non-typed metadata so the validator skips strict checks.
        $this->formMetadataProvider->method('getMetadata')->willReturn($this->createMock(MetadataInterface::class));
        $this->mapperMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        // Provide Sulu's native SEO/excerpt field names so the mapper places them correctly.
        $this->mapperMetadataProvider->method('getMetadata')->willReturnCallback(
            fn (string $key) => match ($key) {
                'content_seo_metadata' => $this->makeFormMeta(['seo/title', 'seo/description', 'seo/keywords', 'seo/canonicalUrl', 'seoNoIndex', 'seoNoFollow', 'seoHideInSitemap']),
                'content_excerpt_metadata' => $this->makeFormMeta(['excerpt/title', 'excerpt/more', 'excerpt/description', 'excerpt/icon', 'excerpt/image']),
                'content_excerpt_taxonomies' => $this->makeFormMeta(['excerptCategories', 'excerptTags']),
                default => $this->makeFormMeta([]),
            },
        );
        $this->tool = new ArticleCreateTool(
            $this->messageBus,
            $this->contentManager,
            new BlockDataValidator($this->formMetadataProvider),
            $this->blockIdGenerator,
            new ContentMetadataMapper($this->mapperMetadataProvider),
        );
    }

    /** @param list<string> $names */
    private function makeFormMeta(array $names): FormMetadata
    {
        $items = [];
        foreach ($names as $name) {
            $field = $this->createMock(FieldMetadata::class);
            $field->method('getName')->willReturn($name);
            $items[$name] = $field;
        }
        $form = $this->createMock(FormMetadata::class);
        $form->method('getItems')->willReturn($items);

        return $form;
    }

    /** @return array<string, mixed> */
    private function pageContent(): array
    {
        return [
            'page' => [
                'path' => '/blog',
                'uuid' => 'parent-page-uuid',
                'suffix' => 'my-article',
            ],
        ];
    }

    public function testCreateArticleDispatchesCreateArticleMessage(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('article-uuid-123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreateArticleMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test Article', 'url' => '/my-article']);

        $result = $this->tool->createArticle('en', 'blog', 'Test Article', null, ['url' => '/my-article']);

        $this->assertTrue($result['success']);
        $this->assertSame('article-uuid-123', $result['uuid']);
    }

    public function testCreateArticleIncludesTypeInData(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $capturedMessage = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle, &$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['url' => '/my-article']);

        $this->tool->createArticle('en', 'blog', 'Test', 'default', ['url' => '/my-article']);

        $this->assertInstanceOf(CreateArticleMessage::class, $capturedMessage);
    }

    public function testCreateArticleMergesContentIntoData(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockArticle, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['url' => '/my-article']);

        $result = $this->tool->createArticle(
            'en',
            'blog',
            'Test',
            null,
            ['article' => '<p>Content</p>', 'url' => '/my-article'],
        );

        $this->assertTrue($result['success']);
    }

    public function testCreateArticleAcceptsPageTreeRoute(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreateArticleMessage::class, $message);
                $this->assertSame([
                    'url' => [
                        'page' => [
                            'path' => '/blog',
                            'uuid' => 'parent-page-uuid',
                        ],
                        'suffix' => 'my-article',
                    ],
                    'locale' => 'en',
                    'template' => 'blog',
                    'title' => 'Test',
                ], $message->getData());

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'url' => [
                'page' => [
                    'path' => '/blog',
                    'uuid' => 'parent-page-uuid',
                ],
                'suffix' => 'my-article',
            ],
        ]);

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, $this->pageContent());

        $this->assertTrue($result['success']);
    }

    public function testCreateArticleAcceptsSuluNativePageTreeRoute(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $route = [
            'page' => [
                'path' => '/blog',
                'uuid' => 'parent-page-uuid',
            ],
            'suffix' => '/my-article',
        ];

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle, $route) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreateArticleMessage::class, $message);
                $this->assertSame($route, $message->getData()['url']);
                $this->assertArrayNotHasKey('page', $message->getData());

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['url' => $route]);

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, ['url' => $route]);

        $this->assertTrue($result['success']);
    }

    public function testCreateArticleResolvesAndNormalizesResult(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockArticle, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->expects($this->once())
            ->method('resolve')
            ->with($mockArticle, [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ])
            ->willReturn($mockDimensionContent);

        $this->contentManager->expects($this->once())
            ->method('normalize')
            ->with($mockDimensionContent)
            ->willReturn(['title' => 'Resolved Title', 'url' => '/my-article']);

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, ['url' => '/my-article']);

        $this->assertSame(['title' => 'Resolved Title', 'url' => '/my-article'], $result['data']);
    }

    public function testCreateArticleReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Article creation failed'));

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, ['url' => '/my-article']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Article creation failed', $result['error']);
        $this->assertArrayHasKey('hint', $result);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreateArticleRejectsMissingRouting(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createArticle('en', 'blog', 'Test');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('routing data', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreateArticleRejectsBothRoutingForms(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, \array_merge(
            ['url' => '/my-article'],
            $this->pageContent(),
        ));

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('both', $result['error']);
    }

    public function testCreateArticleRejectsIncompletePageRouting(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, [
            'page' => ['path' => '/blog', 'uuid' => 'page-uuid'], // missing suffix
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('suffix', $result['error']);
    }

    public function testCreateArticleRejectsRelativeUrl(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, ['url' => 'my-article']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('start with', $result['error']);
    }

    public function testCreateArticleReportsErrorWhenPostCreateUrlIsNull(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockArticle, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'X', 'url' => null]);

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, ['url' => '/my-article']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('url resolved to null', $result['error']);
        $this->assertSame('uuid-1', $result['uuid']);
    }

    public function testCreateArticleMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleCreateTool::class, 'createArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createArticle() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_article_create', $instance->name);
    }

    public function testTypeParameterHasSchemaAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleCreateTool::class, 'createArticle');
        $parameter = $reflection->getParameters()[3];
        $this->assertSame('type', $parameter->getName());

        $attributes = $parameter->getAttributes(Schema::class);
        $this->assertCount(1, $attributes);
    }

    public function testCreateArticleAssignsBlockIdsToNestedBlocks(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $capturedData = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle, &$capturedData) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreateArticleMessage::class, $message);
                $capturedData = $message->getData();

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['url' => '/my-article']);

        $this->tool->createArticle('en', 'blog', 'Test', null, [
            'url' => '/my-article',
            'blocks' => [
                [
                    'type' => 'section',
                    'title' => 'My Section',
                    'blocks' => [
                        ['type' => 'text', 'title' => 'Nested Text'],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($capturedData);
        $blocks = $capturedData['blocks'];
        $this->assertNotEmpty($blocks[0]['_id'], 'top-level block must have a non-empty _id');
        $this->assertNotEmpty($blocks[0]['blocks'][0]['_id'], 'nested block must have a non-empty _id');
    }

    public function testCreateArticleRejectsInvalidBlocksBeforeWrite(): void
    {
        // Build a TypedFormMetadata fixture: template "blog" with a "blocks" block field
        // exposing block type "text" with field [title].
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');

        $textBlock = new FormMetadata();
        $textBlock->setKey('text');
        $textBlock->addItem($titleField);

        $blocksField = new FieldMetadata('blocks');
        $blocksField->setType('block');
        $blocksField->addType($textBlock);

        $template = new FormMetadata();
        $template->setKey('blog');
        $template->addItem($blocksField);

        $typed = new TypedFormMetadata();
        $typed->addForm('blog', $template);

        $this->formMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        $this->formMetadataProvider->method('getMetadata')
            ->willReturnCallback(fn (string $key) => 'article' === $key ? $typed : null);

        $this->tool = new ArticleCreateTool(
            $this->messageBus,
            $this->contentManager,
            new BlockDataValidator($this->formMetadataProvider),
            $this->blockIdGenerator,
            new ContentMetadataMapper($this->mapperMetadataProvider),
        );

        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createArticle('en', 'blog', 'Test', null, [
            'url' => '/my-article',
            'blocks' => [
                ['type' => 'text', 'bogus' => 'invalid-key'],
            ],
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('bogus', $result['error']);
    }

    public function testCreateArticleSetsExcerptAndSeoInDispatchedData(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $capturedMessage = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle, &$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['url' => '/my-article']);

        $this->tool->createArticle(
            'en',
            'blog',
            'Test',
            null,
            ['url' => '/my-article'],
            ['title' => 'T', 'image' => ['id' => 5]],
            ['title' => 'S', 'seoNoIndex' => true],
        );

        $this->assertInstanceOf(CreateArticleMessage::class, $capturedMessage);
        $data = $capturedMessage->getData();
        $this->assertSame('T', $data['excerpt']['title']);
        $this->assertSame(['id' => 5], $data['excerpt']['image']);
        $this->assertSame('S', $data['seo']['title']);
        $this->assertTrue($data['seoNoIndex']);
    }
}
