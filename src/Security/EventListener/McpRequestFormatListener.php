<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Forces the request format to "json" for MCP requests.
 *
 * The MCP route has no `_format` and defaults to "html", so Sulu's MarkupBundle
 * runs HtmlMarkupParser on the response. `<sulu:...>` snippets in JSON-RPC output
 * (e.g. sulu_get_context) make the parser recurse until the stack exhausts (500).
 * "json" has no markup parser, so the response is left untouched.
 *
 * @internal
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
