<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\SecurityBundle\Entity\AccessControl;
use Sulu\Bundle\SecurityBundle\Entity\Permission;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots the bundle's tests/Application kernel against a throwaway sqlite file
 * with only the security tables (User/Role/UserRole/Permission/AccessControl)
 * created via SchemaTool -- avoids docker/MySQL and the full PHPCR/content
 * entity graph.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    /**
     * @var class-string[]
     */
    private const SECURITY_ENTITIES = [
        User::class,
        Role::class,
        UserRole::class,
        Permission::class,
        AccessControl::class,
    ];

    protected EntityManagerInterface $entityManager;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();
        $this->entityManager = $entityManager;

        $this->dbPath = $container->getParameter('kernel.project_dir').'/var/data_test.db';
        if (\file_exists($this->dbPath)) {
            \unlink($this->dbPath);
        }

        $metadata = \array_map(
            $this->entityManager->getClassMetadata(...),
            self::SECURITY_ENTITIES,
        );

        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    /**
     * Adds further entity tables to the throwaway schema, for tests that need real
     * content rows (e.g. a real page tree) rather than a repository double.
     *
     * @param class-string ...$entities
     */
    protected function createSchemaFor(string ...$entities): void
    {
        $metadata = \array_values(\array_map($this->entityManager->getClassMetadata(...), $entities));

        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        parent::tearDown();

        if (isset($this->dbPath) && \file_exists($this->dbPath)) {
            \unlink($this->dbPath);
        }
    }
}
