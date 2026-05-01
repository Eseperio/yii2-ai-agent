<?php

namespace eseperio\aiagent\services;

class FakeOpenAiResponseFactory
{
    public function create(array $body): array
    {
        $input = json_encode($body['input'] ?? []);
        if (is_string($input) && str_contains($input, 'auto-tool-many')) {
            return $this->autoToolManyResponse();
        }
        if (is_string($input) && str_contains($input, 'function-call-auto')) {
            return $this->autoFunctionCallResponse();
        }
        if (is_string($input) && str_contains($input, 'auto-tool')) {
            return $this->autoToolResponse();
        }
        if (is_string($input) && str_contains($input, 'class-demo-tool')) {
            return $this->classToolResponse();
        }
        if (is_string($input) && str_contains($input, 'tool-call')) {
            return $this->toolCallResponse('resp_fake_tool', 'tool requested', 'call_1');
        }

        $scenario = $body['scenario'] ?? null;
        if ($scenario === null) {
            $scenario = 'simple';
        }

        return match ($scenario) {
            'tool_call' => $this->toolCallResponse(),
            'function_call' => $this->functionCallResponse(),
            'after_tool_result' => $this->afterToolResultResponse(),
            'error' => [
                'error' => [
                    'message' => 'Simulated error',
                    'type' => 'invalid_request_error',
                ],
            ],
            default => $this->simpleResponse(),
        };
    }

    private function simpleResponse(): array
    {
        return [
            'id' => 'resp_' . uniqid(),
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['response' => 'fake-response']),
                ]],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }

    private function toolCallResponse(?string $id = null, string $text = 'fake-tool-call', ?string $callId = null): array
    {
        $toolCall = [
            'type' => 'tool_call',
            'name' => 'demo_tool',
            'arguments' => ['value' => 1],
        ];
        if ($callId !== null) {
            $toolCall['id'] = $callId;
        }

        return [
            'id' => $id ?? 'resp_' . uniqid(),
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => $text],
                    $toolCall,
                ],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }

    private function functionCallResponse(): array
    {
        return [
            'id' => 'resp_' . uniqid(),
            'status' => 'completed',
            'output' => [[
                'type' => 'function_call',
                'name' => 'demo_tool',
                'arguments' => ['value' => 1],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 0, 'total_tokens' => 1],
        ];
    }

    private function afterToolResultResponse(): array
    {
        return [
            'id' => 'resp_' . uniqid(),
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['response' => 'fake-after-tool-result']),
                ]],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }

    private function autoToolManyResponse(): array
    {
        $content = [[
            'type' => 'output_text',
            'text' => 'auto tool requested',
        ]];
        for ($i = 1; $i <= 10; $i++) {
            $content[] = [
                'type' => 'tool_call',
                'name' => 'auto_demo_tool',
                'arguments' => ['value' => $i],
                'id' => 'call_auto_' . $i,
            ];
        }

        return [
            'id' => 'resp_fake_auto_many',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => $content,
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }

    private function autoFunctionCallResponse(): array
    {
        return [
            'id' => 'resp_fake_function_auto',
            'status' => 'completed',
            'output' => [[
                'type' => 'function_call',
                'name' => 'auto_demo_tool',
                'arguments' => ['value' => 3],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 0, 'total_tokens' => 1],
        ];
    }

    private function autoToolResponse(): array
    {
        return [
            'id' => 'resp_fake_auto',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'auto tool requested',
                ], [
                    'type' => 'tool_call',
                    'name' => 'auto_demo_tool',
                    'arguments' => ['value' => 2],
                    'id' => 'call_auto_1',
                ]],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }

    private function classToolResponse(): array
    {
        return [
            'id' => 'resp_fake_class_tool',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'class tool requested',
                ], [
                    'type' => 'tool_call',
                    'name' => 'class_demo_tool',
                    'arguments' => ['value' => 7],
                    'id' => 'call_class_1',
                ]],
            ]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ];
    }
}
