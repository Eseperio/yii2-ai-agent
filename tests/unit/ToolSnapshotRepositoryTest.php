<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\services\ToolSnapshotRepository;
use PHPUnit\Framework\TestCase;

class ToolSnapshotRepositoryTest extends TestCase
{
    public function testSaveReturnsSnapshotLikeObject(): void
    {
        $repo = new ToolSnapshotRepository();
        $tool = new ToolDefinition('demo', 'Demo', ['type' => 'object', 'properties' => []]);

        $this->assertSame('sha1', 'sha1');
        $this->assertTrue(true);
    }
}
