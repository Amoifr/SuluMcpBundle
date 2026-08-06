<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\McpServerBundle\Security\Permission\ArticleSecurityContextResolver;

#[CoversClass(ArticleSecurityContextResolver::class)]
final class ArticleSecurityContextResolverTest extends TestCase
{
    public function testDefaultGroupYieldsBaseContext(): void
    {
        $groupProvider = $this->createMock(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([
            (new FormGroup('default', 'Default'))->withTemplate('default'),
        ]);
        $resolver = new ArticleSecurityContextResolver($groupProvider);

        self::assertSame('sulu.article.articles', $resolver->forTemplateKey('default'));
    }

    public function testNamedGroupYieldsSuffixedContext(): void
    {
        $groupProvider = $this->createMock(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([
            (new FormGroup('default', 'Default'))->withTemplate('default'),
            (new FormGroup('blog', 'Blog'))->withTemplate('blog_article'),
        ]);
        $resolver = new ArticleSecurityContextResolver($groupProvider);

        self::assertSame('sulu.article.articles_blog', $resolver->forTemplateKey('blog_article'));
    }

    public function testUnmatchedTemplateInMultiGroupInstallYieldsUnresolvableContext(): void
    {
        $groupProvider = $this->createMock(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([
            (new FormGroup('default', 'Default'))->withTemplate('default'),
            (new FormGroup('blog', 'Blog'))->withTemplate('blog_article'),
        ]);
        $resolver = new ArticleSecurityContextResolver($groupProvider);

        self::assertSame('', $resolver->forTemplateKey('orphaned_template'));
    }

    public function testCandidatesYieldsOnlyBaseContextForSingleGroup(): void
    {
        $groupProvider = $this->createMock(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([
            (new FormGroup('default', 'Default'))->withTemplate('default'),
        ]);
        $resolver = new ArticleSecurityContextResolver($groupProvider);

        self::assertSame(['sulu.article.articles'], $resolver->candidates());
    }

    public function testCandidatesYieldsBaseAndPerGroupContexts(): void
    {
        $groupProvider = $this->createMock(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([
            (new FormGroup('default', 'Default'))->withTemplate('default'),
            (new FormGroup('blog', 'Blog'))->withTemplate('blog_article'),
        ]);
        $resolver = new ArticleSecurityContextResolver($groupProvider);

        self::assertSame(['sulu.article.articles', 'sulu.article.articles_blog'], $resolver->candidates());
    }
}
