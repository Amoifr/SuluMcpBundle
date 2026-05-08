<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Resource\BlocksResource;
use Sulu\McpServerBundle\Resource\TemplatesResource;
use Sulu\McpServerBundle\Resource\WebspacesResource;

class GetContextTool
{
    public function __construct(
        private readonly TemplatesResource $templatesResource,
        private readonly BlocksResource $blocksResource,
        private readonly WebspacesResource $webspacesResource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_get_context',
        description: 'Aggregates all CMS context into a single response. Returns templates, block types, and webspaces for the given webspace. Call this once before creating or editing content to get full CMS awareness.',
    )]
    public function getContext(string $webspace): array
    {
        $templates = $this->templatesResource->getTemplates();
        $blocks = $this->blocksResource->getBlocks();
        $webspaces = $this->webspacesResource->getWebspaces();

        return ['templates' => $templates, 'blocks' => $blocks, 'webspaces' => $webspaces];
    }
}
