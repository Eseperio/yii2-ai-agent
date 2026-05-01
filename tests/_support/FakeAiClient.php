<?php

namespace eseperio\aiagent\tests;

use eseperio\aiagent\services\AiClientInterface;

class FakeAiClient implements AiClientInterface
{
    public function createResponse(array $payload): array
    {
        $input = json_encode($payload['input'] ?? []);
        if (is_string($input) && str_contains($input, 'tool-call')) {
            return [
                'id' => 'resp_fake_tool',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'tool requested',
                    ], [
                        'type' => 'tool_call',
                        'name' => 'demo_tool',
                        'arguments' => ['value' => 1],
                        'id' => 'call_1',
                    ]],
                ]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
            ];
        }

        return [
            'id' => 'resp_fake',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'ok',
                ]],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }
}
