<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Repository;

use Sulu\McpServerBundle\Entity\CompanyContext;

interface CompanyContextRepositoryInterface
{
    public function add(CompanyContext $entity): void;

    public function remove(CompanyContext $entity): void;

    /** @param array<string, mixed> $filters */
    public function findOneBy(array $filters): ?CompanyContext;
}
