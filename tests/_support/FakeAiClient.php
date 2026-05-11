<?php

namespace eseperio\aiagent\tests;

use eseperio\aiagent\services\AiClientInterface;

class FakeAiClient implements AiClientInterface
{
    public function createImageGeneration(array $payload): array
    {
        return $this->fakeImageResponse();
    }

    public function createImageEdit(array $fields, array $imageFiles): array
    {
        return $this->fakeImageResponse();
    }

    public function createResponse(array $payload): array
    {
        $input = json_encode($payload['input'] ?? []);
        if (is_string($input) && str_contains($input, 'generate-image')) {
            return [
                'id' => 'resp_fake_image',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => '{"response":"Genero una imagen para revisar.","conversation_title_suggestion":"Imagen","questionnaire":{"enabled":false,"title":"","description":"","questions":[]}}',
                    ], [
                        'type' => 'tool_call',
                        'name' => 'generate_image',
                        'arguments' => ['prompt' => 'Imagen de prueba'],
                        'id' => 'call_image_1',
                    ]],
                ]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
            ];
        }

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

    private function fakeImageResponse(): array
    {
        return [
            'created' => time(),
            'data' => [[
                'b64_json' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            ]],
        ];
    }
}
