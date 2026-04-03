<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Page\Domain\Repository\NavigationRepositoryInterface;

class NavigationGetTool
{
    public function __construct(
        private readonly NavigationRepositoryInterface $navigationRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * TEMPORARILY DISABLED: Navigation tool has issues with Sulu's internal 'nav' key resolution.
     * The tool exists but is not exposed as an MCP tool until the issue is fully resolved.
     *
     * #[McpTool(
     *     name: 'sulu_navigation_get',
     *     description: 'Get navigation tree for a webspace...',
     * )]
     */
    public function getNavigation(string $webspace, string $locale, string $navigationContext = 'main', int $depth = 2): array
    {
        try {
            // Validate parameters before calling repository
            if ('' === $webspace || '' === $locale) {
                return [
                    'error' => 'Webspace and locale parameters are required.',
                    'hint' => 'Provide valid webspace key (e.g., "example") and locale (e.g., "en").',
                ];
            }

            // Pass minimal properties to ensure 'nav' key is resolved in the content
            // The properties array is prefixed with 'nav.' by Sulu's NavigationRepository
            $properties = ['title' => '', 'url' => ''];

            $tree = $this->navigationRepository->getNavigationTree(
                $navigationContext,
                $locale,
                $webspace,
                null,
                $depth,
                $properties,
            );

            return [
                'navigation' => $tree,
                'webspace' => $webspace,
                'locale' => $locale,
                'context' => $navigationContext,
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $trace = $e->getTraceAsString();

            // Provide helpful hint based on error type
            $hint = 'Verify the webspace exists and the navigationContext matches a context defined in config/webspaces/*.xml.';
            if (\str_contains($message, 'Undefined array key')) {
                // Extract the missing key from error message
                \preg_match('/Undefined array key "([^"]+)"/', $message, $matches);
                $missingKey = $matches[1] ?? 'unknown';
                $hint = 'The navigation context "'.$navigationContext.'" exists, but Sulu is looking for a key "'.$missingKey.'" that is missing. This may be a configuration issue in the webspace XML under \u003cnavigation\u003e → \u003ccontexts\u003e → \u003ccontext key="'.$navigationContext.'"\u003e.';
            }

            return [
                'error' => \sprintf('Failed to get navigation for webspace "%s": %s', $webspace, $message),
                'hint' => $hint,
                'debug' => [
                    'webspace' => $webspace,
                    'locale' => $locale,
                    'navigationContext' => $navigationContext,
                    'exception_class' => $e::class,
                ],
            ];
        }
    }
}
