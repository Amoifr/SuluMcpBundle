<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\AdminLink\Provider;

use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\McpBundle\AdminLink\AdminLinkContextTrait;
use Sulu\Bundle\McpBundle\AdminLink\AdminLinkProviderInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;

/**
 * @internal
 */
final readonly class PageAdminLinkProvider implements AdminLinkProviderInterface
{
    use AdminLinkContextTrait;

    public function __construct(
        private ViewRegistry $viewRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'page';
    }

    public function buildPath(array $context): ?string
    {
        $webspace = $this->requireString($context, 'webspace');
        $locale = $this->requireString($context, 'locale');
        $uuid = $this->requireString($context, 'uuid');

        if (in_array(null, [$webspace, $locale, $uuid], true)) {
            return null;
        }

        return $this->resolveViewPath($this->viewRegistry, PageAdmin::EDIT_FORM_VIEW, [
            ':webspace' => $webspace,
            ':locale' => $locale,
            ':id' => $uuid,
        ]);
    }
}
