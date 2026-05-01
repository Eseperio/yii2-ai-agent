<?php

namespace eseperio\aiagent\tests\handlers;

use eseperio\aiagent\contracts\ToolHandlerInterface;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;

class ClassDemoToolHandler implements ToolHandlerInterface
{
    public function execute(ToolExecutionContext $context, array $arguments): ToolResult
    {
        return new ToolResult(true, ['handled' => $arguments['value'] ?? null], null, [], [], 'handled');
    }
}
