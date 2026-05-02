<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\ToolContext;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\services\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    public function testNormalizeConvertsToolDefinitions(): void
    {
        $registry = new ToolRegistry();
        $normalized = $registry->normalize([
            new ToolDefinition('demo', 'Demo tool', ['type' => 'object', 'properties' => []]),
        ]);

        $this->assertSame('function', $normalized[0]['type']);
        $this->assertSame('demo', $normalized[0]['name']);
        $this->assertSame('Demo tool', $normalized[0]['description']);
        $this->assertTrue($normalized[0]['strict']);
    }

    public function testNormalizeRejectsDuplicateNames(): void
    {
        $registry = new ToolRegistry();
        $this->expectException(\RuntimeException::class);

        $registry->normalize([
            new ToolDefinition('demo', 'Demo 1', ['type' => 'object', 'properties' => []]),
            new ToolDefinition('demo', 'Demo 2', ['type' => 'object', 'properties' => []]),
        ]);
    }

    public function testNormalizeAllowsNonStrictToolDefinitions(): void
    {
        $registry = new ToolRegistry();
        $normalized = $registry->normalize([
            new ToolDefinition('demo', 'Demo tool', ['type' => 'object', 'properties' => []], metadata: ['strict' => false]),
        ]);

        $this->assertFalse($normalized[0]['strict']);
    }

    public function testFindResolvedByNameRejectsAmbiguousRegistryMatches(): void
    {
        \Yii::$app->setModule('aiAgent', [
            'class' => \eseperio\aiagent\Module::class,
            'tools' => [
                new ToolDefinition('demo', 'Demo 1', ['type' => 'object', 'properties' => []]),
                new ToolDefinition('demo', 'Demo 2', ['type' => 'object', 'properties' => []]),
            ],
            'permissions' => ['canViewChat' => true],
        ]);

        $registry = new ToolRegistry();
        $this->expectException(\RuntimeException::class);
        $registry->findResolvedByName('demo', new ToolContext());
    }

    public function testContextualToolsRequireMatchingContext(): void
    {
        \Yii::$app->setModule('aiAgent', [
            'class' => \eseperio\aiagent\Module::class,
            'tools' => [
                new ToolDefinition('product_tool', 'Product tool', ['type' => 'object', 'properties' => []], null, false, null, [10]),
            ],
            'permissions' => ['canViewChat' => true],
        ]);

        $registry = new ToolRegistry();

        $this->assertSame([], $registry->getTools(new ToolContext(contexts: [])));
        $this->assertCount(1, $registry->getTools(new ToolContext(contexts: [
            ['type' => 10, 'metadata' => ['id' => 1]],
        ])));
    }

    public function testManualsExposeInternalReadTools(): void
    {
        \Yii::$app->setModule('aiAgent', [
            'class' => \eseperio\aiagent\Module::class,
            'manuals' => [
                new \eseperio\aiagent\dto\ManualDefinition(
                    'demo.manual',
                    'Demo manual',
                    'Demo procedure',
                    'Do the demo steps.'
                ),
            ],
            'permissions' => ['canViewChat' => true],
        ]);

        $registry = new ToolRegistry();
        $toolNames = array_map(static fn(ToolDefinition $tool): string => $tool->name, $registry->getTools(new ToolContext()));

        $this->assertContains('list_agent_manuals', $toolNames);
        $this->assertContains('read_agent_manual', $toolNames);
    }
}
