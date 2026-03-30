<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sulu\McpServerBundle\Entity\CompanyContext;

class CompanyContextRepository implements CompanyContextRepositoryInterface
{
    /** @var EntityRepository<CompanyContext> */
    private readonly EntityRepository $entityRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->entityRepository = $entityManager->getRepository(CompanyContext::class);
    }

    public function add(CompanyContext $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function remove(CompanyContext $entity): void
    {
        $this->entityManager->remove($entity);
    }

    /** @param array<string, mixed> $filters */
    public function findOneBy(array $filters): ?CompanyContext
    {
        /** @var CompanyContext|null $result */
        $result = $this->entityRepository->findOneBy($filters);

        return $result;
    }
}
