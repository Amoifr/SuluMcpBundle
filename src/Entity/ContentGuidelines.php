<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sulu_mcp_content_guidelines')]
#[ORM\UniqueConstraint(name: 'uniq_guidelines_webspace', columns: ['webspace'])]
class ContentGuidelines
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore-next-line property.unusedType */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    private ?string $webspace = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $audience = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $style = null;

    #[ORM\Column(name: 'brand_rules', type: 'text', nullable: true)]
    private ?string $brandRules = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dos = null;

    #[ORM\Column(name: 'donts', type: 'text', nullable: true)]
    private ?string $donts = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebspace(): ?string
    {
        return $this->webspace;
    }

    public function setWebspace(?string $webspace): static
    {
        $this->webspace = $webspace;

        return $this;
    }

    public function getTone(): ?string
    {
        return $this->tone;
    }

    public function setTone(?string $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    public function getAudience(): ?string
    {
        return $this->audience;
    }

    public function setAudience(?string $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function setStyle(?string $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function getBrandRules(): ?string
    {
        return $this->brandRules;
    }

    public function setBrandRules(?string $brandRules): static
    {
        $this->brandRules = $brandRules;

        return $this;
    }

    public function getDos(): ?string
    {
        return $this->dos;
    }

    public function setDos(?string $dos): static
    {
        $this->dos = $dos;

        return $this;
    }

    public function getDonts(): ?string
    {
        return $this->donts;
    }

    public function setDonts(?string $donts): static
    {
        $this->donts = $donts;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'webspace' => $this->webspace,
            'tone' => $this->tone,
            'audience' => $this->audience,
            'style' => $this->style,
            'brand_rules' => $this->brandRules,
            'dos' => $this->dos,
            "don'ts" => $this->donts,
        ];
    }
}
