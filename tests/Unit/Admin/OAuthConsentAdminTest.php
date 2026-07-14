<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\McpServerBundle\Admin\OAuthConsentAdmin;

#[CoversClass(OAuthConsentAdmin::class)]
final class OAuthConsentAdminTest extends TestCase
{
    public function testConfiguresConsentViewUsingCoreAuthorizationConsentType(): void
    {
        $viewCollection = new ViewCollection();
        $admin = new OAuthConsentAdmin(new ViewBuilderFactory());

        $admin->configureViews($viewCollection);

        self::assertTrue($viewCollection->has(OAuthConsentAdmin::CONSENT_VIEW));

        $view = $viewCollection->get(OAuthConsentAdmin::CONSENT_VIEW)->getView();
        self::assertSame('/mcp/authorize/:requestId', $view->getPath());
        self::assertSame('sulu_admin.authorization_consent', $view->getType());
        self::assertSame('sulu_mcp_server_oauth_consent_details', $view->getOption('detailsRoute'));
        self::assertSame('sulu_mcp_server_oauth_consent_decision', $view->getOption('decisionRoute'));
    }
}
