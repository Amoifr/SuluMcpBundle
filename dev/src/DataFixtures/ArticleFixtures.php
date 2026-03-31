<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Domain\Model\Page;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ArticleFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        #[Autowire(service: 'sulu_message_bus')]
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getDependencies(): array
    {
        return [PageFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Page $blogPage */
        $blogPage = $this->getReference(PageFixtures::BLOG_PAGE_REFERENCE, Page::class);
        $blogPageUuid = $blogPage->getUuid();

        foreach ($this->getArticlesData($blogPageUuid) as $articleData) {
            $this->createAndPublishArticle($articleData);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAndPublishArticle(array $data): void
    {
        $envelope = $this->messageBus->dispatch(
            new Envelope(
                new CreateArticleMessage($data),
                [new EnableFlushStamp()],
            ),
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var ArticleInterface $article */
        $article = $handledStamps[0]->getResult();

        $this->messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionArticleMessage(
                    identifier: ['uuid' => $article->getUuid()],
                    locale: 'en',
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                [new EnableFlushStamp()],
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getArticlesData(string $blogPageUuid): array
    {
        return [
            [
                'locale' => 'en',
                'title' => 'Getting Started with MCP and Sulu',
                'template' => 'article',
                'url' => [
                    'page' => ['uuid' => $blogPageUuid, 'path' => '/blog'],
                    'suffix' => '/getting-started-with-mcp-and-sulu',
                ],
                'article' => '<p>A comprehensive guide to connecting AI assistants to your Sulu CMS instance via the Model Context Protocol.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'What is MCP?'],
                    ['type' => 'text', 'content' => '<p>The Model Context Protocol (MCP) is an open standard that allows AI assistants to interact with external tools and data sources. By implementing MCP in Sulu, we enable AI assistants to create, read, update, and publish content — all while respecting your existing permissions and workflows.</p>'],
                    ['type' => 'heading', 'title' => 'Prerequisites'],
                    ['type' => 'text', 'content' => '<p>Before you begin, make sure you have Sulu 3.0 or later installed, PHP 8.2+, and a Sulu admin user account. The MCP bundle requires Symfony 7.3+ which is supported by Sulu 3.0.</p>'],
                    ['type' => 'heading', 'title' => 'Installation'],
                    ['type' => 'text', 'content' => '<p>Install the MCP server bundle via Composer. The bundle registers automatically and exposes an MCP endpoint at <code>/_mcp</code> by default. Configure your AI assistant to connect to this endpoint with your Sulu admin credentials.</p>'],
                    ['type' => 'quote', 'text' => '<p>MCP uses Streamable HTTP transport — no WebSocket or SSE required. It works behind load balancers and proxies out of the box.</p>', 'attribution' => 'MCP Specification'],
                ],
            ],
            [
                'locale' => 'en',
                'title' => 'AI Content Workflows: Best Practices',
                'template' => 'article',
                'url' => [
                    'page' => ['uuid' => $blogPageUuid, 'path' => '/blog'],
                    'suffix' => '/ai-content-workflows-best-practices',
                ],
                'article' => '<p>How to design effective content workflows that combine human creativity with AI efficiency.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'The Human-AI Content Loop'],
                    ['type' => 'text', 'content' => '<p>The most effective content workflows do not replace human editors — they augment them. AI assistants excel at drafting, formatting, and publishing content, while humans provide creative direction, brand voice, and editorial judgment.</p>'],
                    ['type' => 'quote', 'text' => '<p>Think of AI as a skilled intern: fast, tireless, and eager to help — but always needing editorial oversight and brand guidance.</p>', 'attribution' => 'Content Strategy Team'],
                    ['type' => 'heading', 'title' => 'Setting Up Content Guidelines'],
                    ['type' => 'text', 'content' => '<p>Content guidelines are the bridge between human intent and AI execution. Define your brand voice, tone, terminology, and formatting rules. The MCP server makes these guidelines available to AI assistants as context, ensuring on-brand content from the first draft.</p>'],
                    ['type' => 'heading', 'title' => 'Review and Publish Workflow'],
                    ['type' => 'text', 'content' => '<p>A solid workflow looks like this: AI creates a draft, human reviews and refines, AI publishes the approved version. Sulu\'s draft/publish workflow maps perfectly to this pattern — AI assistants can create drafts without publishing, giving editors full control over what goes live.</p>'],
                ],
            ],
            [
                'locale' => 'en',
                'title' => 'Understanding Sulu Block Templates',
                'template' => 'article',
                'url' => [
                    'page' => ['uuid' => $blogPageUuid, 'path' => '/blog'],
                    'suffix' => '/understanding-sulu-block-templates',
                ],
                'article' => '<p>Block templates are the building blocks of flexible content in Sulu CMS.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'What Are Blocks?'],
                    ['type' => 'text', 'content' => '<p>Blocks are reusable content components that editors can arrange freely within a page. Unlike fixed templates, blocks give content creators the flexibility to compose pages from modular pieces — headings, text sections, images, quotes, and more.</p>'],
                    ['type' => 'heading', 'title' => 'Defining Block Types'],
                    ['type' => 'text', 'content' => '<p>Block types are defined in XML template files. Each type specifies its properties, validation rules, and metadata. Sulu supports shared block definitions via XML includes — define a block type once in <code>config/templates/blocks/</code> and reference it across multiple templates with <code>&lt;type ref="block_name"/&gt;</code>.</p>'],
                    ['type' => 'quote', 'text' => '<p>Shared block definitions keep your templates DRY and ensure consistency across your content types.</p>', 'attribution' => 'Sulu Documentation'],
                    ['type' => 'heading', 'title' => 'Rendering Blocks in Twig'],
                    ['type' => 'text', 'content' => '<p>In your Twig templates, iterate over blocks and include type-specific partials. Each block type gets its own template file in <code>templates/blocks/</code>, keeping rendering logic modular and maintainable.</p>'],
                ],
            ],
            [
                'locale' => 'en',
                'title' => 'Multi-Webspace Content Strategy',
                'template' => 'article',
                'url' => [
                    'page' => ['uuid' => $blogPageUuid, 'path' => '/blog'],
                    'suffix' => '/multi-webspace-content-strategy',
                ],
                'article' => '<p>Managing content across multiple webspaces and locales with AI assistance.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'One CMS, Multiple Brands'],
                    ['type' => 'text', 'content' => '<p>Sulu\'s webspace architecture allows you to manage multiple websites from a single CMS installation. Each webspace can have its own templates, content guidelines, and locale configuration. The MCP server exposes all webspaces to AI assistants, letting them manage content across brands.</p>'],
                    ['type' => 'heading', 'title' => 'Locale-Aware Content Creation'],
                    ['type' => 'text', 'content' => '<p>When creating content via MCP, AI assistants must specify both the webspace and locale. The server validates these parameters against your Sulu configuration, preventing content from being created in invalid combinations.</p>'],
                    ['type' => 'text', 'content' => '<p>This validation ensures content integrity even when AI assistants are processing requests at scale. Every operation goes through the same permission checks that apply in the Sulu admin interface.</p>'],
                    ['type' => 'quote', 'text' => '<p>Security is not optional — every MCP operation uses the authenticated Sulu user\'s permissions. No privilege escalation, no shortcuts.</p>', 'attribution' => 'Security Architecture'],
                ],
            ],
        ];
    }
}
