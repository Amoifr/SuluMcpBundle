<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security\OAuth;

use League\OAuth2\Server\Entities\UserEntityInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\McpServerBundle\Security\OAuth\SuluOAuthUser;
use Sulu\McpServerBundle\Security\OAuth\SuluUserResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SuluUserResolverTest extends TestCase
{
    private SuluUserResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SuluUserResolver();
    }

    public function testResolveFromSecurityTokenReturnsSuluOAuthUserWithUsername(): void
    {
        $suluUser = $this->createMock(User::class);
        $suluUser->method('getUserIdentifier')->willReturn('admin');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($suluUser);

        $oauthUser = $this->resolver->resolveFromSecurityToken($token);

        $this->assertInstanceOf(UserEntityInterface::class, $oauthUser);
        $this->assertInstanceOf(SuluOAuthUser::class, $oauthUser);
        $this->assertSame('admin', $oauthUser->getIdentifier());
    }

    public function testResolveFromSecurityTokenThrowsForNonSuluUser(): void
    {
        $genericUser = $this->createMock(UserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($genericUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected Sulu User entity');

        $this->resolver->resolveFromSecurityToken($token);
    }
}
