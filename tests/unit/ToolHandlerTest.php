<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\Module;
use eseperio\aiagent\contracts\ToolHandlerInterface;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use eseperio\aiagent\services\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolHandlerStub implements ToolHandlerInterface
{
    public function execute(ToolExecutionContext $context, array $arguments): ToolResult
    {
        return new ToolResult(true, ['handled' => $arguments['value'] ?? null], null, [], [], 'handled');
    }
}

class ToolHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Yii::$app->setModule('aiAgent', [
            'class' => Module::class,
            'tools' => [
                new ToolDefinition('class_tool', 'Class tool', ['type' => 'object', 'properties' => []], ToolHandlerStub::class),
                new ToolDefinition('callable_tool', 'Callable tool', ['type' => 'object', 'properties' => []], static fn($context, array $arguments) => new ToolResult(true, ['called' => $arguments['value'] ?? null], null, [], [], 'called')),
            ],
            'permissions' => ['canViewChat' => true, 'canExecuteTool' => true],
        ]);
    }

    public function testRegistryResolvesCallableAndClassHandlers(): void
    {
        $registry = new ToolRegistry();
        $context = new \eseperio\aiagent\dto\ToolContext();
        $tools = $registry->getTools($context);

        $this->assertCount(2, $tools);
        $this->assertSame('class_tool', $tools[0]->name);
        $this->assertSame('callable_tool', $tools[1]->name);
    }
}
