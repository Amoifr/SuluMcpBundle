<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\PreviewBundle\SuluPreviewBundle;
use Sulu\McpServerBundle\Capabilities\Tool\Preview\PreviewLinkGenerateTool;
use Sulu\McpServerBundle\DependencyInjection\Compiler\PreviewLinkAvailabilityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(PreviewLinkAvailabilityPass::class)]
final class PreviewLinkAvailabilityPassTest extends TestCase
{
    public function testKeepsToolWhenPreviewBundleIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SuluPreviewBundle' => SuluPreviewBundle::class]);
        $container->setDefinition(PreviewLinkGenerateTool::class, new Definition(PreviewLinkGenerateTool::class));

        (new PreviewLinkAvailabilityPass())->process($container);

        $this->assertTrue($container->hasDefinition(PreviewLinkGenerateTool::class));
    }

    public function testRemovesToolWhenPreviewBundleIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SomeOtherBundle' => 'App\\SomeOtherBundle']);
        $container->setDefinition(PreviewLinkGenerateTool::class, new Definition(PreviewLinkGenerateTool::class));

        (new PreviewLinkAvailabilityPass())->process($container);

        $this->assertFalse($container->hasDefinition(PreviewLinkGenerateTool::class));
    }

    public function testNoOpWhenToolIsNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new PreviewLinkAvailabilityPass())->process($container);

        $this->assertFalse($container->hasDefinition(PreviewLinkGenerateTool::class));
    }
}
