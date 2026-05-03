<?php

namespace eseperio\aiagent\actions\chat;

class GetHistoryAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->get('conversation_id', 0);
        if (!$this->can('canViewHistory', $this->permissionContext('getHistory', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $messages = $this->module()?->getConversationManager()->getMessagesForDisplay($conversationId) ?? [];
        $messages = $this->appendMissingContextPreviewMessages($conversationId, $messages);
        return $this->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    private function appendMissingContextPreviewMessages(int $conversationId, array $messages): array
    {
        $module = $this->module();
        if (!$module || $conversationId <= 0) {
            return $messages;
        }

        $existingContextIds = [];
        foreach ($messages as $message) {
            if (($message['message_type'] ?? null) !== 'context') {
                continue;
            }
            $payload = json_decode((string)($message['content'] ?? ''), true);
            if (is_array($payload) && isset($payload['id'])) {
                $existingContextIds[(int)$payload['id']] = true;
            }
        }

        foreach ($module->getContextManager()->listContexts($conversationId) as $context) {
            if (isset($existingContextIds[(int)$context->id])) {
                continue;
            }
            $messages[] = [
                'id' => 'context-preview-' . (int)$context->id,
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'message_type' => 'context',
                'content' => json_encode($this->renderContextPreviewPayload($conversationId, $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_id' => null,
                'tool_call_id' => null,
                'tool_name' => null,
                'tool_payload' => null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'created_at' => (int)($context->created_at ?? time()),
                'updated_at' => (int)($context->updated_at ?? time()),
                'virtual' => true,
            ];
        }

        return $messages;
    }
}
