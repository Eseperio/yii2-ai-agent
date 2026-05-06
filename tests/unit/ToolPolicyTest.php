<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\Module;
use eseperio\aiagent\services\ToolPolicy;
use PHPUnit\Framework\TestCase;

class ToolPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Yii::$app->setModule('aiAgent', [
            'class' => Module::class,
            'clientConfig' => ['apiKey' => 'test'],
            'enabled' => true,
        ]);
    }

    public function testReadToolCanRunAutonomously(): void
    {
        $decision = (new ToolPolicy())->decide(new ToolDefinition(
            'read_tool',
            'Read',
            ['type' => 'object', 'properties' => []],
            null,
            false,
            null,
            [],
            null,
            ['effect' => 'read']
        ));

        $this->assertTrue($decision->allowed);
        $this->assertTrue($decision->allowAutonomous);
        $this->assertFalse($decision->requiresApproval);
    }

    public function testBlockedEffectsAreDenied(): void
    {
        $decision = (new ToolPolicy())->decide(new ToolDefinition(
            'delete_tool',
            'Delete',
            ['type' => 'object', 'properties' => []],
            null,
            true,
            null,
            [],
            null,
            ['effect' => 'delete']
        ));

        $this->assertFalse($decision->allowed);
        $this->assertSame('tool_effect_blocked', $decision->reason);
    }
}
