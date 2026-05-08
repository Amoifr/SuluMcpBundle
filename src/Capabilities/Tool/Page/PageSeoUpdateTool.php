<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageSeoUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_seo_update',
        description: 'Update SEO metadata on a page draft. Only pass the fields you want to change — others are preserved. Re-publish via sulu_page_publish to make changes live.',
    )]
    public function updatePageSeo(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null,
        ?string $canonicalUrl = null,
        ?bool $noIndex = null,
        ?bool $noFollow = null,
        ?bool $hideInSitemap = null,
    ): array {
        try {
            $page = $this->pageRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            );

            $dimensionContent = $this->contentManager->resolve($page, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $currentData = $this->contentManager->normalize($dimensionContent);

            /** @var array<string, mixed> $currentSeo */
            $currentSeo = \is_array($currentData['seo'] ?? null) ? $currentData['seo'] : [];

            $seoUpdates = [];
            if (null !== $title) {
                $seoUpdates['title'] = $title;
            }
            if (null !== $description) {
                $seoUpdates['description'] = $description;
            }
            if (null !== $keywords) {
                $seoUpdates['keywords'] = $keywords;
            }
            if (null !== $canonicalUrl) {
                $seoUpdates['canonicalUrl'] = $canonicalUrl;
            }

            $mergedSeo = \array_merge($currentSeo, $seoUpdates);

            $data = [
                'locale' => $locale,
                'template' => $currentData['template'] ?? null,
                'title' => $currentData['title'] ?? null,
                'seo' => $mergedSeo,
                'seoNoIndex' => $noIndex ?? ($currentData['seoNoIndex'] ?? false),
                'seoNoFollow' => $noFollow ?? ($currentData['seoNoFollow'] ?? false),
                'seoHideInSitemap' => $hideInSitemap ?? ($currentData['seoHideInSitemap'] ?? false),
            ];

            $data = $this->stringifyKeys($data);

            $message = new ModifyPageMessage(['uuid' => $uuid], $data);
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'seo' => $mergedSeo,
                'seoNoIndex' => $data['seoNoIndex'],
                'seoNoFollow' => $data['seoNoFollow'],
                'seoHideInSitemap' => $data['seoHideInSitemap'],
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update SEO for page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_page_seo_get).',
            ];
        }
    }
}
