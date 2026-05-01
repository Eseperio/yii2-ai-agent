<?php

namespace eseperio\aiagent\services;

use yii\base\Component;

class ResponseParser extends Component
{
    public function parse(array $response): array
    {
        $text = null;
        $toolCalls = [];
        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? '') === 'message') {
                foreach (($item['content'] ?? []) as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $text = ($text ?? '') . ($content['text'] ?? '');
                    } elseif (($content['type'] ?? '') === 'tool_call') {
                        $toolCalls[] = $content;
                    }
                }
            } elseif (($item['type'] ?? '') === 'tool_call') {
                $toolCalls[] = $item;
            } elseif (($item['type'] ?? '') === 'function_call') {
                $toolCalls[] = $item;
            }
        }

        return [
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
            'text' => $text,
            'tool_calls' => $toolCalls,
            'usage' => $response['usage'] ?? [],
        ];
    }

    public function parseText(string $text): array
    {
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : ['text' => $text];
    }
}
