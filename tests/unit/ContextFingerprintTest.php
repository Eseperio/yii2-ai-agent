<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\services\ContextRenderer;
use PHPUnit\Framework\TestCase;

class ContextFingerprintTest extends TestCase
{
    public function testResolveFingerprintNormalizesContextMetadata(): void
    {
        $renderer = new ContextRenderer();

        $first = $renderer->resolveFingerprint([
            ['type' => 10, 'metadata' => ['id' => 123, 'class' => 'Product']],
            ['type' => 20, 'metadata' => ['class' => 'Cms', 'id' => 7]],
        ]);

        $second = $renderer->resolveFingerprint([
            ['type' => 20, 'metadata' => ['id' => 7, 'class' => 'Cms']],
            ['type' => 10, 'metadata' => ['class' => 'Product', 'id' => 123]],
        ]);

        $this->assertSame($first, $second);
    }
}
