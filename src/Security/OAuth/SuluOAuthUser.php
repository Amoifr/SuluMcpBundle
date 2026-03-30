<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\OAuth;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Minimal OAuth user entity that bridges League OAuth2 Server with Sulu.
 *
 * The identifier is the Sulu user ID, which allows resolving the full
 * Sulu User entity later for permission checks.
 */
class SuluOAuthUser implements UserEntityInterface
{
    use EntityTrait;

    public function __construct(string $identifier)
    {
        $this->setIdentifier($identifier);
    }
}
