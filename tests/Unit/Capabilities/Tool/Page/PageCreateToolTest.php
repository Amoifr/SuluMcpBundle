<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\AdminLink\AdminLinkGenerator;
use Sulu\McpServerBundle\AdminLink\Provider\PageAdminLinkProvider;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\ContentMetadataMapper;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageCreateTool;
use Sulu\McpServerBundle\Tests\Support\StubViewRegistry;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(PageCreateTool::class)]
final class PageCreateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private MetadataProviderInterface&MockObject $formMetadataProvider;
    private MetadataProviderInterface&MockObject $mapperMetadataProvider;
    private BlockIdGeneratorInterface&MockObject $blockIdGenerator;
    private AdminLinkGenerator $adminLinkGenerator;
    private PageCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
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
        $this->blockIdGenerator = $this->createMock(BlockIdGeneratorInterface::class);
        $this->blockIdGenerator->method('generateId')->willReturn('gen-id');

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('https://example.com/admin/');
        $this->adminLinkGenerator = new AdminLinkGenerator($router, [new PageAdminLinkProvider(new StubViewRegistry())]);

        $this->tool = new PageCreateTool(
            $this->messageBus,
            $this->contentManager,
            new BlockDataValidator($this->formMetadataProvider),
            $this->blockIdGenerator,
            new ContentMetadataMapper($this->mapperMetadataProvider),
            $this->adminLinkGenerator,
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

    public function testCreatePageDispatchesCreatePageMessage(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('page-uuid-123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test Page']);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test Page', 'parent-uuid');

        $this->assertTrue($result['success']);
        $this->assertSame('page-uuid-123', $result['uuid']);
    }

    public function testCreatePageIncludesLocaleInData(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage) {
                /** @var CreatePageMessage $message */
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');
    }

    public function testCreatePageGeneratesUrlFromTitleWhenUrlIsNull(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $capturedMessage = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage, &$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage('example', 'en', 'default', 'My Test Page', 'parent-uuid');

        $this->assertInstanceOf(CreatePageMessage::class, $capturedMessage);
    }

    public function testCreatePageMergesContentIntoData(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $result = $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            ['excerpt' => 'Test excerpt'],
        );

        $this->assertTrue($result['success']);
    }

    public function testCreatePageResolvesAndNormalizesResult(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->expects($this->once())
            ->method('resolve')
            ->with($mockPage, [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ])
            ->willReturn($mockDimensionContent);

        $this->contentManager->expects($this->once())
            ->method('normalize')
            ->with($mockDimensionContent)
            ->willReturn(['title' => 'Resolved Title']);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertSame(['title' => 'Resolved Title'], $result['data']);
    }

    public function testCreatePageReturnsSuccessWithUuid(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('new-page-uuid');

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertTrue($result['success']);
        $this->assertSame('new-page-uuid', $result['uuid']);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame(
            'https://example.com/admin/#/webspaces/example/pages/en/new-page-uuid',
            $result['admin_url'],
        );
    }

    public function testCreatePageReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Page creation failed'));

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Page creation failed', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreatePageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageCreateTool::class, 'createPage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createPage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_create', $instance->name);
    }

    public function testCreatePageAssignsBlockIdsToNestedBlocks(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $capturedData = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage, &$capturedData) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);
                $capturedData = (new \ReflectionProperty($message, 'data'))->getValue($message);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            [
                'blocks' => [
                    ['type' => 'text', 'title' => 'A'],
                    ['type' => 'section', 'title' => 'S', 'blocks' => [
                        ['type' => 'text', 'title' => 'N'],
                    ]],
                ],
            ],
        );

        $this->assertNotNull($capturedData);
        $blocks = $capturedData['blocks'];
        $this->assertNotEmpty($blocks[0]['_id']);
        $this->assertNotEmpty($blocks[1]['_id']);
        $this->assertNotEmpty($blocks[1]['blocks'][0]['_id']);
    }

    public function testCreatePageRejectsInvalidBlocksBeforeWrite(): void
    {
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');
        $textBlock = new FormMetadata();
        $textBlock->setKey('text');
        $textBlock->addItem($titleField);

        $blocksField = new FieldMetadata('blocks');
        $blocksField->setType('block');
        $blocksField->addType($textBlock);

        $template = new FormMetadata();
        $template->setKey('default');
        $template->addItem($blocksField);

        $typed = new TypedFormMetadata();
        $typed->addForm('default', $template);

        $this->formMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        $this->formMetadataProvider->method('getMetadata')
            ->willReturnCallback(fn (string $key) => 'page' === $key ? $typed : null);

        $this->tool = new PageCreateTool(
            $this->messageBus,
            $this->contentManager,
            new BlockDataValidator($this->formMetadataProvider),
            $this->blockIdGenerator,
            new ContentMetadataMapper($this->mapperMetadataProvider),
            $this->adminLinkGenerator,
        );

        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            ['blocks' => [['type' => 'text', 'bogus' => 'x']]],
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('bogus', $result['error']);
    }

    public function testCreatePageReturnsMapperErrorWithoutDispatchingWhenUnknownSeoField(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            null,
            null,
            ['bogusField' => 'x'],
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('bogusField', $result['error']);
    }

    public function testCreatePageAppliesExcerptAndSeoToDispatchedMessage(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $capturedData = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage, &$capturedData) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);
                $capturedData = (new \ReflectionProperty($message, 'data'))->getValue($message);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            null,
            ['title' => 'T', 'description' => '<p>D</p>', 'image' => ['id' => 5]],
            ['title' => 'S', 'description' => 'meta', 'seoNoIndex' => true],
        );

        $this->assertNotNull($capturedData);
        $this->assertSame('T', $capturedData['excerpt']['title']);
        $this->assertSame(['id' => 5], $capturedData['excerpt']['image']);
        $this->assertSame('S', $capturedData['seo']['title']);
        $this->assertTrue($capturedData['seoNoIndex']);
    }
}
