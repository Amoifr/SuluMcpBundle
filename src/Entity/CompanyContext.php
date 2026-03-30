<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sulu_mcp_company_context')]
class CompanyContext
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore-next-line property.unusedType */
    private ?int $id = null;

    #[ORM\Column(name: 'company_name', type: 'text', nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $website = null;

    #[ORM\Column(name: 'key_products', type: 'text', nullable: true)]
    private ?string $keyProducts = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): static
    {
        $this->industry = $industry;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getKeyProducts(): ?string
    {
        return $this->keyProducts;
    }

    public function setKeyProducts(?string $keyProducts): static
    {
        $this->keyProducts = $keyProducts;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'description' => $this->description,
            'industry' => $this->industry,
            'website' => $this->website,
            'key_products' => $this->keyProducts,
        ];
    }
}
