<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Resource\BlocksResource;
use Sulu\McpServerBundle\Resource\CompanyContextResource;
use Sulu\McpServerBundle\Resource\GuidelinesResource;
use Sulu\McpServerBundle\Resource\TemplatesResource;
use Sulu\McpServerBundle\Resource\WebspacesResource;

class GetContextTool
{
    public function __construct(
        private readonly TemplatesResource $templatesResource,
        private readonly BlocksResource $blocksResource,
        private readonly WebspacesResource $webspacesResource,
        private readonly GuidelinesResource $guidelinesResource,
        private readonly CompanyContextResource $companyContextResource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_get_context',
        description: 'Aggregates all CMS context into a single response. Returns templates, block types, webspaces, content guidelines, company context, and sitemap for the given webspace. Call this once before creating or editing content to get full CMS awareness.',
    )]
    public function getContext(string $webspace): array
    {
        $templates = $this->templatesResource->getTemplates();
        $blocks = $this->blocksResource->getBlocks();
        $webspaces = $this->webspacesResource->getWebspaces();
        $guidelines = $this->guidelinesResource->getGuidelines($webspace);
        $company_context = $this->companyContextResource->getCompanyContext();

        return ['templates' => $templates, 'blocks' => $blocks, 'webspaces' => $webspaces, 'guidelines' => $guidelines, 'company_context' => $company_context];
    }
}
