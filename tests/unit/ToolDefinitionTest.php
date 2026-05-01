<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\ToolDefinition;
use PHPUnit\Framework\TestCase;

class ToolDefinitionTest extends TestCase
{
    public function testConstructorExposesToolContract(): void
    {
        $tool = new ToolDefinition(
            'demo',
            'Demo tool',
            ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
            static fn() => null,
            true,
            'provider-demo',
            [10, 20],
            static fn() => true,
            ['scope' => 'test']
        );

        $this->assertSame('demo', $tool->name);
        $this->assertSame('Demo tool', $tool->description);
        $this->assertSame('provider-demo', $tool->providerId);
        $this->assertTrue($tool->requiresApproval);
        $this->assertSame([10, 20], $tool->contextTypes);
        $this->assertSame(['scope' => 'test'], $tool->metadata);
    }
}
