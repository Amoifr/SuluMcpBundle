<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\AdminLink\Provider;

use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\CategoryBundle\Admin\CategoryAdmin;
use Sulu\McpServerBundle\AdminLink\AdminLinkContextTrait;
use Sulu\McpServerBundle\AdminLink\AdminLinkProviderInterface;

/**
 * @internal
 */
final readonly class CategoryAdminLinkProvider implements AdminLinkProviderInterface
{
    use AdminLinkContextTrait;

    public function __construct(
        private ViewRegistry $viewRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'category';
    }

    public function buildPath(array $context): ?string
    {
        $locale = $this->requireString($context, 'locale');
        $id = $this->requireId($context, 'id');

        if (null === $locale || null === $id) {
            return null;
        }

        return $this->resolveViewPath($this->viewRegistry, CategoryAdmin::EDIT_FORM_VIEW, [
            ':locale' => $locale,
            ':id' => $id,
        ]);
    }
}
