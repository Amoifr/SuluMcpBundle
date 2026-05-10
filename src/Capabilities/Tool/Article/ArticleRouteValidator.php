<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

/**
 * Validates the URL routing fields passed in article create/update content.
 *
 * Sulu article templates use either a simple `route` property (flat URL string)
 * or a `page_tree_route` property (nested page reference + suffix). The MCP
 * layer used to forward both forms to Sulu without checking, which caused:
 *   - create silently returning `url: null` when the wrong form was used,
 *   - update throwing a cryptic `extractRouteSlug()` type error from vendor.
 *
 * This validator runs on the MCP side so the LLM gets an actionable message
 * before the message hits Sulu's RoutableDataMapper.
 */
final class ArticleRouteValidator
{
    /**
     * Validate a routing payload supplied to article create/update.
     *
     * @param array<string, mixed> $content  Normalized article content
     * @param bool                 $required When true (create), having no routing form is an error
     *
     * @return array<string, mixed>|null Error payload, or null when the content is valid
     */
    public static function validate(array $content, bool $required): ?array
    {
        $hasUrl = \array_key_exists('url', $content);
        $hasPage = \array_key_exists('page', $content);

        if (!$hasUrl && !$hasPage) {
            if (!$required) {
                return null;
            }

            return self::error(
                'Article content is missing routing data. Pass either content={"url": "/my-article"} (simple route templates) or content={"page": {"path": "/blog", "uuid": "<page-uuid>", "suffix": "my-article"}} (page_tree_route templates). Call sulu_get_context to see which form your template expects -- look for a field of type "route" or "page_tree_route" in the template schema.'
            );
        }

        if ($hasUrl && $hasPage) {
            return self::error(
                'Article content has both "url" and "page" routing fields. Pass exactly one form depending on the template (use sulu_get_context to check).'
            );
        }

        if ($hasUrl) {
            $url = $content['url'];
            if (!\is_string($url) || '' === $url) {
                return self::error('Article content.url must be a non-empty string, e.g. "/my-article".');
            }
            if (!\str_starts_with($url, '/')) {
                return self::error(\sprintf('Article content.url must start with "/". Got: %s', $url));
            }

            return null;
        }

        // hasPage
        $page = $content['page'];
        if (!\is_array($page)) {
            return self::error('Article content.page must be an object with keys "path", "uuid", and "suffix".');
        }

        foreach (['path', 'uuid', 'suffix'] as $key) {
            $value = $page[$key] ?? null;
            if (!\is_string($value) || '' === $value) {
                return self::error(\sprintf(
                    'Article content.page.%s must be a non-empty string. Required keys: path (parent URL), uuid (parent page UUID), suffix (article slug).',
                    $key,
                ));
            }
        }

        return null;
    }

    /**
     * After a create call, check whether routing actually resolved.
     *
     * Sulu silently produces `url: null` when the supplied routing form does
     * not match the template's route property type. Catch that here and turn
     * it into a clear error the LLM can act on.
     *
     * @param array<string, mixed> $normalizedArticle Output of ContentManager::normalize()
     * @param array<string, mixed> $content           The routing content the caller supplied
     *
     * @return array<string, string>|null
     */
    public static function assertRoutingResolved(array $normalizedArticle, array $content): ?array
    {
        $hasUrl = \array_key_exists('url', $content);
        $hasPage = \array_key_exists('page', $content);

        if (!$hasUrl && !$hasPage) {
            return null;
        }

        $resolvedUrl = $normalizedArticle['url'] ?? null;
        if (\is_string($resolvedUrl) && '' !== $resolvedUrl) {
            return null;
        }

        $suggestion = $hasUrl
            ? 'Tried content.url but the template likely uses page_tree_route. Retry with content={"page": {"path": "...", "uuid": "...", "suffix": "..."}}.'
            : 'Tried content.page but the template likely uses a simple route. Retry with content={"url": "/<full-path>"}.';

        return self::error(
            'Article was created but routing was dropped (url resolved to null). This usually means the URL form does not match the template\'s route property type. '.$suggestion.' Call sulu_get_context to inspect the template field types.'
        );
    }

    /** @return array<string, string> */
    private static function error(string $message): array
    {
        return [
            'error' => $message,
            'hint' => 'Use sulu_get_context to inspect template fields. Look for a property with type "route" (use content.url) or "page_tree_route" (use content.page).',
        ];
    }
}
