<?php

namespace eseperio\aiagent\contracts;

use eseperio\aiagent\dto\ToolContext;

interface ToolProviderInterface
{
    public function getTools(ToolContext $context): array;
}
