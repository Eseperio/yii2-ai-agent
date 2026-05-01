<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\services\ResponseParser;
use PHPUnit\Framework\TestCase;

class ResponseParserTest extends TestCase
{
    public function testParseTextReturnsDecodedJson(): void
    {
        $parser = new ResponseParser();
        $parsed = $parser->parseText('{"response":"ok"}');
        $this->assertSame('ok', $parsed['response']);
    }

    public function testParseResponseExtractsToolCalls(): void
    {
        $parser = new ResponseParser();
        $parsed = $parser->parse([
            'id' => 'abc',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'hello'],
                        ['type' => 'tool_call', 'name' => 'demo', 'arguments' => []],
                    ],
                ],
            ],
        ]);

        $this->assertSame('hello', $parsed['text']);
        $this->assertCount(1, $parsed['tool_calls']);
    }
}
