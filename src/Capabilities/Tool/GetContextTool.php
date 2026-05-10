<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Capabilities\Resource\BlocksResource;
use Sulu\McpServerBundle\Capabilities\Resource\TemplatesResource;
use Sulu\McpServerBundle\Capabilities\Resource\WebspacesResource;

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
        description: 'Aggregates all CMS context into a single response. Returns templates (grouped by content type: `page`, `article`, `snippet`), block types, and webspaces. Call this once before creating or editing content to get full CMS awareness — including which article templates are available and which URL routing form each template expects (look at the field with type `route` or `page_tree_route`).',
    )]
    public function getContext(string $webspace): array
    {
        $templates = $this->templatesResource->getTemplates();
        $blocks = $this->blocksResource->getBlocks();
        $webspaces = $this->webspacesResource->getWebspaces();

        return ['templates' => $templates, 'blocks' => $blocks, 'webspaces' => $webspaces];
    }
}
