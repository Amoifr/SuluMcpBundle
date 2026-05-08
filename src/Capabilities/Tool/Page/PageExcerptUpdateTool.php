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

class PageExcerptUpdateTool
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
        name: 'sulu_page_excerpt_update',
        description: 'Update the excerpt (teaser) on a page draft. Only pass the fields you want to change — others are preserved. Pass imageId/iconId as media ids (use sulu_media_list to find them). Re-publish via sulu_page_publish to make changes live.',
    )]
    public function updatePageExcerpt(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $description = null,
        ?string $more = null,
        ?int $imageId = null,
        ?int $iconId = null,
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

            /** @var array<string, mixed> $currentExcerpt */
            $currentExcerpt = \is_array($currentData['excerpt'] ?? null) ? $currentData['excerpt'] : [];

            $excerptUpdates = [];
            if (null !== $title) {
                $excerptUpdates['title'] = $title;
            }
            if (null !== $description) {
                $excerptUpdates['description'] = $description;
            }
            if (null !== $more) {
                $excerptUpdates['more'] = $more;
            }
            if (null !== $imageId) {
                $excerptUpdates['image'] = ['id' => $imageId];
            }
            if (null !== $iconId) {
                $excerptUpdates['icon'] = ['id' => $iconId];
            }

            $mergedExcerpt = \array_merge($currentExcerpt, $excerptUpdates);

            $data = [
                'locale' => $locale,
                'template' => $currentData['template'] ?? null,
                'title' => $currentData['title'] ?? null,
                'excerpt' => $mergedExcerpt,
            ];

            $data = $this->stringifyKeys($data);

            $message = new ModifyPageMessage(['uuid' => $uuid], $data);
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'excerpt' => $mergedExcerpt,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update excerpt for page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_page_excerpt_get) and that imageId/iconId reference real media (use sulu_media_list).',
            ];
        }
    }
}
