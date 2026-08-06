<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Functional;

use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\SqlWalker;
use Oro\ORM\Query\AST\Functions\Cast as OroCastFunction;
use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;
use Sulu\McpServerBundle\Security\Permission\PageDescendantPermissionChecker;
use Sulu\Page\Domain\Model\Page;

/**
 * Oro's CAST DQL function only emits MySQL/PostgreSQL SQL (FunctionFactory
 * throws "Not supported platform" otherwise), so AccessControlRepository's
 * CAST(...) query can't run against sqlite. Reuses Oro's parser and swaps in
 * SQLite's native CAST syntax, registered for this test only.
 */
final class SqliteCastFunction extends OroCastFunction
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        $value = $this->parameters[self::PARAMETER_KEY];
        $expression = $value instanceof Node ? $value->dispatch($sqlWalker) : (string) $value;

        $type = \strtolower((string) $this->parameters[self::TYPE_KEY]);
        $sqliteType = \str_starts_with($type, 'int') || 'bigint' === $type ? 'INTEGER' : 'TEXT';

        return "CAST({$expression} AS {$sqliteType})";
    }
}

/**
 * Tier B case 5: PageDescendantPermissionChecker end to end against a real
 * persisted page tree and the real AccessControlRepository -- not a
 * repository double, so tree traversal is genuinely exercised.
 */
final class PageDescendantAclTest extends FunctionalTestCase
{
    private const ALL_GRANTED = [
        PermissionTypes::VIEW => true, PermissionTypes::ADD => true, PermissionTypes::EDIT => true,
        PermissionTypes::DELETE => true, PermissionTypes::ARCHIVE => true, PermissionTypes::LIVE => true,
        PermissionTypes::SECURITY => true,
    ];

    private const VIEW_ONLY = [
        PermissionTypes::VIEW => true, PermissionTypes::ADD => false, PermissionTypes::EDIT => false,
        PermissionTypes::DELETE => false, PermissionTypes::ARCHIVE => false, PermissionTypes::LIVE => false,
        PermissionTypes::SECURITY => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchemaFor(Page::class);
    }

    public function testThrowsWhenOneDescendantDenied(): void
    {
        [$parent, , $deniedChild] = $this->createPageTree();

        $builder = $this->fixtureBuilder();
        // Broad DELETE grant at webspace level; children inherit unless overridden per-page.
        $role = $builder->role('PageDeleter', ['sulu.webspaces.website' => self::ALL_GRANTED]);
        $builder->objectAcl(Page::class, $deniedChild->getUuid(), $role, self::VIEW_ONLY);
        $builder->authenticate($builder->user('page-deleter', $role));

        $this->expectException(PermissionDeniedException::class);
        $this->checker()->assertCanDeleteDescendants($parent->getUuid());
    }

    public function testReturnsSilentlyWhenAllDescendantsGranted(): void
    {
        [$parent, $grantedChild] = $this->createPageTree();

        $builder = $this->fixtureBuilder();
        $role = $builder->role('PageDeleterAll', ['sulu.webspaces.website' => self::ALL_GRANTED]);
        // Explicit ALLOW row proves the grant via the real ACL query, not just the no-row default.
        $builder->objectAcl(Page::class, $grantedChild->getUuid(), $role, self::ALL_GRANTED);
        $builder->authenticate($builder->user('page-deleter-all', $role));

        $this->checker()->assertCanDeleteDescendants($parent->getUuid());
        $this->addToAssertionCount(1);
    }

    /**
     * Pins the half a repository double used to hide: Sulu's real tree traversal
     * must actually return both children of the real parent row.
     */
    public function testRealRepositoryResolvesTheDescendantTree(): void
    {
        [$parent, $grantedChild, $deniedChild] = $this->createPageTree();

        $repository = self::getContainer()->get('sulu_page.page_repository');

        $descendants = $repository->findDescendantIdsById($parent->getUuid());
        \sort($descendants);

        $expected = [$grantedChild->getUuid(), $deniedChild->getUuid()];
        \sort($expected);

        self::assertSame($expected, $descendants);
    }

    /**
     * @return array{Page, Page, Page} parent, granted child, denied child
     */
    private function createPageTree(): array
    {
        $parent = $this->createPage();
        $grantedChild = $this->createPage($parent);
        $deniedChild = $this->createPage($parent);

        $this->entityManager->flush();

        return [$parent, $grantedChild, $deniedChild];
    }

    private function createPage(?Page $parent = null): Page
    {
        $page = new Page();
        $page->setWebspaceKey('website');

        if ($parent instanceof Page) {
            $page->setParent($parent);
        }

        $this->entityManager->persist($page);

        return $page;
    }

    private function fixtureBuilder(): PermissionFixtureBuilder
    {
        $container = self::getContainer();

        return new PermissionFixtureBuilder(
            $this->entityManager,
            $container->get('sulu_security.mask_converter'),
            $container->get('security.token_storage'),
            $container->get(SystemStoreInterface::class),
        );
    }

    private function checker(): PageDescendantPermissionChecker
    {
        $container = self::getContainer();

        // Registers the sqlite CAST shim (see SqliteCastFunction) for this query.
        $this->entityManager->getConfiguration()->addCustomStringFunction('CAST', SqliteCastFunction::class);

        return new PageDescendantPermissionChecker(
            $container->get('sulu_page.page_repository'),
            $container->get('sulu.repository.access_control'),
            $container->get(SystemStoreInterface::class),
            $container->get('security.helper'),
            $container->getParameter('sulu_security.permissions'),
        );
    }
}
