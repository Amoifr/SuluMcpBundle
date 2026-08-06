<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Tests\Support;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;

/**
 * Stub GroupProviderInterface: returns the configured groups regardless of the requested key.
 */
final readonly class StubGroupProvider implements GroupProviderInterface
{
    /**
     * @param array<string, FormGroup> $groups
     */
    public function __construct(
        private array $groups = [],
    ) {
    }

    public function getGroups(string $key): array
    {
        return $this->groups;
    }

    /**
     * A single 'default' group with the 'article' template, reproducing the
     * single-group install every existing test implicitly assumes.
     */
    public static function singleGroup(): self
    {
        return new self([
            'default' => new FormGroup('default', 'Default', ['article']),
        ]);
    }
}
