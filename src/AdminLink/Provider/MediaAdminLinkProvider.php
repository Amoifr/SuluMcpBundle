<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\AdminLink\Provider;

use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\McpBundle\AdminLink\AdminLinkContextTrait;
use Sulu\Bundle\McpBundle\AdminLink\AdminLinkProviderInterface;
use Sulu\Bundle\MediaBundle\Admin\MediaAdmin;

/**
 * @internal
 */
final readonly class MediaAdminLinkProvider implements AdminLinkProviderInterface
{
    use AdminLinkContextTrait;

    public function __construct(
        private ViewRegistry $viewRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'media';
    }

    public function buildPath(array $context): ?string
    {
        $locale = $this->requireString($context, 'locale');
        $id = $this->requireId($context, 'id');

        if (null === $locale || null === $id) {
            return null;
        }

        return $this->resolveViewPath($this->viewRegistry, MediaAdmin::EDIT_FORM_VIEW, [
            ':locale' => $locale,
            ':id' => $id,
        ]);
    }
}
