<?php

namespace eseperio\aiagent\contracts;

use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;

interface ToolHandlerInterface
{
    public function execute(ToolExecutionContext $context, array $arguments): ToolResult;
}
