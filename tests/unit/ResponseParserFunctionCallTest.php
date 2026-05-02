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
                ['type' => 'function_call', 'call_id' => 'call_123', 'name' => 'demo', 'arguments' => '{"value":7}'],
            ],
        ]);

        $this->assertCount(1, $parsed['tool_calls']);
        $this->assertSame('call_123', $parsed['tool_calls'][0]['id']);
        $this->assertSame(['value' => 7], $parsed['tool_calls'][0]['arguments']);
    }
}
