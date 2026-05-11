<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Command;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sulu:mcp:create-client',
    description: 'Create an OAuth2 client for MCP connections (Claude.ai, ChatGPT, etc.)',
)]
class CreateMcpClientCommand extends Command
{
    private const CLAUDE_CALLBACK_URI = 'https://claude.ai/api/mcp/auth_callback';

    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly string $serverUrl,
        private readonly string $mcpPath = '/admin/_mcp',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Client name (e.g. "Claude.ai Production")')
            ->addOption('redirect-uri', null, InputOption::VALUE_REQUIRED, 'OAuth callback URI', self::CLAUDE_CALLBACK_URI)
            ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'OAuth scopes', ['mcp:tools', 'mcp:resources'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $redirectUri = $input->getOption('redirect-uri');
        $scopes = $input->getOption('scope');

        $identifier = \bin2hex(\random_bytes(16));
        $secret = \bin2hex(\random_bytes(32));

        $client = new Client($name, $identifier, $secret);
        $client->setRedirectUris(new RedirectUri($redirectUri));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(...\array_map(static fn (string $s) => new Scope($s), $scopes));
        $client->setActive(true);

        $this->clientManager->save($client);

        $io->success('MCP OAuth client created.');

        $io->table(
            ['Setting', 'Value'],
            [
                ['Name', $name],
                ['Client ID', $identifier],
                ['Client Secret', $secret],
                ['Redirect URI', $redirectUri],
                ['Grant Types', 'authorization_code, refresh_token'],
                ['Scopes', \implode(', ', $scopes)],
            ],
        );

        $mcpUrl = \rtrim($this->serverUrl, '/').$this->mcpPath;

        $io->section('Claude.ai Setup');
        $io->listing([
            'Go to Claude.ai → Settings → Connectors → Add Custom Connector',
            \sprintf('Name: %s', $name),
            \sprintf('Remote MCP Server URL: %s', $mcpUrl),
            \sprintf('OAuth Client ID: %s', $identifier),
            \sprintf('OAuth Client Secret: %s', $secret),
        ]);

        $io->warning('Save the Client Secret now — it cannot be retrieved later.');

        return Command::SUCCESS;
    }
}
