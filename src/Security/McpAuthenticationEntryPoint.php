<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Decorates Sulu's authentication entry point to show a friendly login page
 * when unauthenticated users hit the OAuth authorize endpoint.
 *
 * For all other admin routes, delegates to the original Sulu entry point.
 */
class McpAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AuthenticationEntryPointInterface $inner,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (!str_contains($request->getPathInfo(), '/mcp/authorize')) {
            return $this->inner->start($request, $authException);
        }

        $locale = $request->getPreferredLanguage(['en', 'de']) ?? 'en';
        $this->translator->setLocale($locale); // @phpstan-ignore-line

        $html = $this->twig->render('@SuluMcpServerBundle/mcp/login_required.html.twig', [
            'locale' => $locale,
            'admin_url' => '/admin',
            'authorize_url' => $request->getUri(),
        ]);

        return new Response($html, 401, ['Content-Type' => 'text/html']);
    }
}
