<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Resource;

use Mcp\Capability\Attribute\McpResource;
use Sulu\McpServerBundle\Repository\CompanyContextRepositoryInterface;

class CompanyContextResource
{
    public function __construct(
        private readonly CompanyContextRepositoryInterface $repository,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpResource(
        uri: 'sulu://context/company',
        name: 'sulu_company_context',
        description: 'Company and business context for content generation. Includes company name, description, industry, website, and key products.',
        mimeType: 'application/json',
    )]
    public function getCompanyContext(): array
    {
        $entity = $this->repository->findOneBy([]);

        return $entity?->toArray() ?? ['company_name' => null, 'description' => null, 'industry' => null, 'website' => null, 'key_products' => null];
    }
}
