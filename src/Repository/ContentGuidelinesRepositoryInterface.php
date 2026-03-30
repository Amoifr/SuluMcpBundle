<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Repository;

use Sulu\McpServerBundle\Entity\ContentGuidelines;

interface ContentGuidelinesRepositoryInterface
{
    public function add(ContentGuidelines $entity): void;

    public function remove(ContentGuidelines $entity): void;

    /** @param array<string, mixed> $filters */
    public function findOneBy(array $filters): ?ContentGuidelines;
}
