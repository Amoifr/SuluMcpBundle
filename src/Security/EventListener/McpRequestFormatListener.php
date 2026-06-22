<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Forces the request format to "json" for MCP endpoint requests.
 *
 * The MCP route is registered by symfony/mcp-bundle without a `_format`,
 * so requests default to the "html" format. Sulu's MarkupBundle registers a
 * kernel.response listener that runs the HtmlMarkupParser on every response
 * whose request format has a markup parser ("html"). The JSON-RPC response of
 * a tool like `sulu_get_context` contains `<sulu:...>` example snippets that the
 * parser matches but cannot resolve, so HtmlMarkupParser::parse() recurses
 * endlessly and exhausts the stack (HTTP 500).
 *
 * Setting the format to "json" — for which no markup parser is registered —
 * makes the MarkupListener skip MCP responses entirely.
 */
class McpRequestFormatListener
{
    public function __construct(
        private readonly string $mcpPath,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), $this->mcpPath)) {
            return;
        }

        $request->setRequestFormat('json');
    }
}
