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

        $this->assertSame('demo', $normalized[0]['function']['name']);
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
}
