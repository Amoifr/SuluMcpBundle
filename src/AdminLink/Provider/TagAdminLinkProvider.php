<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\AdminLink\Provider;

use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\TagBundle\Admin\TagAdmin;
use Sulu\McpServerBundle\AdminLink\AdminLinkContextTrait;
use Sulu\McpServerBundle\AdminLink\AdminLinkProviderInterface;

/**
 * @internal
 */
final readonly class TagAdminLinkProvider implements AdminLinkProviderInterface
{
    use AdminLinkContextTrait;

    public function __construct(
        private ViewRegistry $viewRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'tag';
    }

    public function buildPath(array $context): ?string
    {
        $id = $this->requireId($context, 'id');

        if (null === $id) {
            return null;
        }

        return $this->resolveViewPath($this->viewRegistry, TagAdmin::EDIT_FORM_VIEW, [
            ':id' => $id,
        ]);
    }
}
