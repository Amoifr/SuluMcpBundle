<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\OAuth;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7591 — OAuth 2.0 Dynamic Client Registration.
 *
 * Allows MCP clients (e.g. Claude Code) to register themselves without
 * requiring a pre-provisioned client_id/secret.
 */
class DynamicClientRegistrationController
{
    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly string $serverUrl,
    ) {
    }

    #[Route('/mcp/register', name: 'sulu_mcp_client_registration', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $redirectUris = (array) ($body['redirect_uris'] ?? []);
        if ([] === $redirectUris) {
            return new JsonResponse(
                ['error' => 'invalid_client_metadata', 'error_description' => 'redirect_uris is required'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $clientName = (string) ($body['client_name'] ?? 'MCP Client');
        $grantTypes = (array) ($body['grant_types'] ?? ['authorization_code']);

        $clientId = bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));

        $client = new Client($clientName, $clientId, $clientSecret);

        $client->setRedirectUris(...array_map(
            static fn (string $uri) => new RedirectUri($uri),
            $redirectUris,
        ));

        $client->setGrants(...array_map(
            static fn (string $grant) => new Grant($grant),
            array_merge($grantTypes, ['refresh_token']),
        ));

        $client->setScopes(
            new Scope('mcp:tools'),
            new Scope('mcp:resources'),
        );

        $this->clientManager->save($client);

        $base = rtrim($this->serverUrl, '/');

        return new JsonResponse([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
            'grant_types' => array_values(array_unique([...$grantTypes, 'refresh_token'])),
            'token_endpoint_auth_method' => 'client_secret_post',
            'registration_client_uri' => $base.'/mcp/register/'.$clientId,
        ], Response::HTTP_CREATED);
    }
}
