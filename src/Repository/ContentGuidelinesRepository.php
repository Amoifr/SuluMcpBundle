<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sulu\McpServerBundle\Entity\ContentGuidelines;

class ContentGuidelinesRepository implements ContentGuidelinesRepositoryInterface
{
    /** @var EntityRepository<ContentGuidelines> */
    private readonly EntityRepository $entityRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->entityRepository = $entityManager->getRepository(ContentGuidelines::class);
    }

    public function add(ContentGuidelines $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function remove(ContentGuidelines $entity): void
    {
        $this->entityManager->remove($entity);
    }

    /** @param array<string, mixed> $filters */
    public function findOneBy(array $filters): ?ContentGuidelines
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('entity');

        if (\array_key_exists('webspace', $filters)) {
            $webspace = $filters['webspace'];
            if (null === $webspace) {
                $queryBuilder->andWhere('entity.webspace IS NULL');
            } else {
                $queryBuilder->andWhere('entity.webspace = :webspace')
                    ->setParameter('webspace', $webspace);
            }
        }

        /** @var ContentGuidelines|null $result */
        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return $result;
    }
}
