<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class PageFixtures extends Fixture
{
    public const BLOG_PAGE_REFERENCE = 'page-blog';

    public function __construct(
        #[Autowire(service: 'sulu_message_bus')]
        private readonly MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $homepage = $this->findOrCreateHomepage();
        $homepageUuid = $homepage->getUuid();

        foreach ($this->getChildPagesData() as $pageData) {
            $referenceName = $pageData['_reference'] ?? null;
            unset($pageData['_reference']);
            $this->createAndPublishPage($homepageUuid, $pageData, $referenceName);
        }
    }

    private function findOrCreateHomepage(): PageInterface
    {
        $homepage = $this->pageRepository->findOneBy([
            'parentId' => null,
        ]);

        if (null !== $homepage) {
            $this->addHomepageBlocks($homepage);

            return $homepage;
        }

        // Homepage not yet initialized (fixtures may run before sulu:page:initialize)
        $envelope = $this->messageBus->dispatch(
            new Envelope(
                new CreatePageMessage(
                    webspaceKey: 'website',
                    parentId: 'homepage',
                    data: [
                        'locale' => 'en',
                        'title' => 'Welcome to Sulu MCP',
                        'template' => 'homepage',
                        'url' => '/',
                        'article' => '<p>This is a demo site powered by Sulu CMS with AI content management via the Model Context Protocol.</p>',
                        'blocks' => [
                            ['type' => 'heading', 'title' => 'AI-Powered Content Management'],
                            ['type' => 'text', 'content' => '<p>Sulu MCP connects AI assistants like Claude and ChatGPT directly to your content management system. Create, edit, and publish content using natural language — while respecting your brand guidelines and content structure.</p>'],
                            ['type' => 'quote', 'text' => '<p>The future of content management is conversational. AI assistants should understand your brand, not just execute commands.</p>', 'attribution' => 'Sulu MCP Team'],
                            ['type' => 'heading', 'title' => 'Getting Started'],
                            ['type' => 'text', 'content' => '<p>Connect your AI assistant to the MCP endpoint, authenticate with your Sulu credentials, and start managing content through conversation. All operations respect your existing roles and permissions.</p>'],
                        ],
                    ],
                ),
                [new EnableFlushStamp()],
            ),
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var Page $page */
        $page = $handledStamps[0]->getResult();

        $this->messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    identifier: ['uuid' => $page->getUuid()],
                    locale: 'en',
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                [new EnableFlushStamp()],
            ),
        );

        return $page;
    }

    private function addHomepageBlocks(PageInterface $homepage): void
    {
        $data = [
            'locale' => 'en',
            'title' => 'Welcome to Sulu MCP',
            'template' => 'homepage',
            'article' => '<p>This is a demo site powered by Sulu CMS with AI content management via the Model Context Protocol.</p>',
            'blocks' => [
                ['type' => 'heading', 'title' => 'AI-Powered Content Management'],
                ['type' => 'text', 'content' => '<p>Sulu MCP connects AI assistants like Claude and ChatGPT directly to your content management system. Create, edit, and publish content using natural language — while respecting your brand guidelines and content structure.</p>'],
                ['type' => 'quote', 'text' => '<p>The future of content management is conversational. AI assistants should understand your brand, not just execute commands.</p>', 'attribution' => 'Sulu MCP Team'],
                ['type' => 'heading', 'title' => 'Getting Started'],
                ['type' => 'text', 'content' => '<p>Connect your AI assistant to the MCP endpoint, authenticate with your Sulu credentials, and start managing content through conversation. All operations respect your existing roles and permissions.</p>'],
            ],
        ];

        $this->messageBus->dispatch(
            new Envelope(
                new ModifyPageMessage(
                    identifier: ['uuid' => $homepage->getUuid()],
                    data: $data,
                ),
                [new EnableFlushStamp()],
            ),
        );

        $this->messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    identifier: ['uuid' => $homepage->getUuid()],
                    locale: 'en',
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                [new EnableFlushStamp()],
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAndPublishPage(string $parentId, array $data, ?string $referenceName = null): void
    {
        $envelope = $this->messageBus->dispatch(
            new Envelope(
                new CreatePageMessage(
                    webspaceKey: 'website',
                    parentId: $parentId,
                    data: $data,
                ),
                [new EnableFlushStamp()],
            ),
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var Page $page */
        $page = $handledStamps[0]->getResult();

        $this->messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    identifier: ['uuid' => $page->getUuid()],
                    locale: 'en',
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                [new EnableFlushStamp()],
            ),
        );

        if (null !== $referenceName) {
            $this->addReference($referenceName, $page);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getChildPagesData(): array
    {
        return [
            [
                'locale' => 'en',
                'title' => 'About Us',
                'template' => 'default',
                'url' => '/about',
                'navigationContexts' => ['main'],
                'article' => '<p>Learn more about our company and mission.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'Our Story'],
                    ['type' => 'text', 'content' => '<p>Founded in 2020, we set out to make content management smarter. Our team believes AI should empower content creators, not replace them.</p>'],
                    ['type' => 'quote', 'text' => '<p>The best content management is invisible — it gets out of the way and lets creators create.</p>', 'attribution' => 'Our Founder'],
                    ['type' => 'text', 'content' => '<p>Today we serve hundreds of organizations worldwide, helping them publish content faster and more consistently.</p>'],
                ],
            ],
            [
                'locale' => 'en',
                'title' => 'Our Services',
                'template' => 'default',
                'url' => '/services',
                'navigationContexts' => ['main'],
                'article' => '',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'What We Offer'],
                    ['type' => 'text', 'content' => '<p>We provide a range of content management and AI integration services.</p>'],
                    ['type' => 'heading', 'title' => 'Content Strategy'],
                    ['type' => 'text', 'content' => '<p>Our content strategists help you define your voice, plan your editorial calendar, and measure impact.</p>'],
                    ['type' => 'heading', 'title' => 'AI Integration'],
                    ['type' => 'text', 'content' => '<p>We connect AI assistants directly to your CMS so they can create, edit, and publish on-brand content.</p>'],
                ],
            ],
            [
                '_reference' => self::BLOG_PAGE_REFERENCE,
                'locale' => 'en',
                'title' => 'Blog',
                'template' => 'default',
                'url' => '/blog',
                'navigationContexts' => ['main'],
                'article' => '<p>Latest news and insights from our team.</p>',
                'blocks' => [
                    ['type' => 'article_list', 'title' => 'Recent Articles', 'articles' => ['provider' => 'articles', 'limitResult' => 10, 'sortBy' => 'published', 'sortMethod' => 'desc']],
                ],
            ],
            [
                'locale' => 'en',
                'title' => 'Contact',
                'template' => 'default',
                'url' => '/contact',
                'navigationContexts' => ['main'],
                'article' => '<p>Get in touch with our team.</p>',
                'blocks' => [
                    ['type' => 'heading', 'title' => 'Reach Out'],
                    ['type' => 'text', 'content' => '<p>Whether you have questions about our platform, need help with integration, or want to discuss a partnership, we would love to hear from you.</p>'],
                    ['type' => 'quote', 'text' => '<p>We typically respond within 24 hours on business days.</p>', 'attribution' => 'Support Team'],
                ],
            ],
        ];
    }
}
