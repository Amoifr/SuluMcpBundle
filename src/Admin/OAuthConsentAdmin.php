<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

final class OAuthConsentAdmin extends Admin
{
    public const CONSENT_VIEW = 'sulu_mcp_server.oauth_consent';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $viewCollection->add(
            $this->viewBuilderFactory->createViewBuilder(
                self::CONSENT_VIEW,
                '/mcp/authorize/:requestId',
                'sulu_admin.authorization_consent',
            )
                ->setOption('detailsRoute', 'sulu_mcp_server_oauth_consent_details')
                ->setOption('decisionRoute', 'sulu_mcp_server_oauth_consent_decision'),
        );
    }
}
