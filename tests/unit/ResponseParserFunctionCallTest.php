<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\services\ResponseParser;
use PHPUnit\Framework\TestCase;

class ResponseParserFunctionCallTest extends TestCase
{
    public function testParseFunctionCall(): void
    {
        $parser = new ResponseParser();
        $parsed = $parser->parse([
            'output' => [
                ['type' => 'function_call', 'name' => 'demo', 'arguments' => []],
            ],
        ]);

        $this->assertCount(1, $parsed['tool_calls']);
    }
}
