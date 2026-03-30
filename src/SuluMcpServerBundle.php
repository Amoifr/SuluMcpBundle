<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SuluMcpServerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
