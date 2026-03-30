<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Entity\CompanyContext;
use Sulu\McpServerBundle\Repository\CompanyContextRepositoryInterface;

class UpdateCompanyContextTool
{
    public function __construct(
        private readonly CompanyContextRepositoryInterface $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_update_company_context',
        description: 'Create or update the company and business context used for content generation. Only one row exists — subsequent calls update the existing context. Only provided fields are updated.',
    )]
    public function updateCompanyContext(
        ?string $companyName = null,
        ?string $description = null,
        ?string $industry = null,
        ?string $website = null,
        ?string $keyProducts = null,
    ): array {
        // Singleton row — always update or create the first/only row
        $entity = $this->repository->findOneBy([]) ?? new CompanyContext();

        if (null !== $companyName) {
            $entity->setCompanyName($companyName);
        }
        if (null !== $description) {
            $entity->setDescription($description);
        }
        if (null !== $industry) {
            $entity->setIndustry($industry);
        }
        if (null !== $website) {
            $entity->setWebsite($website);
        }
        if (null !== $keyProducts) {
            $entity->setKeyProducts($keyProducts);
        }

        $this->repository->add($entity);
        $this->entityManager->flush();

        return [
            'success' => true,
            'company_name' => $entity->getCompanyName(),
        ];
    }
}
