<?php

namespace eseperio\aiagent\actions\chat;

class ContinueConversationAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->post('conversation_id', 0);
        if ($conversationId <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        if (!$this->can('canContinueChat', $this->permissionContext('continueConversation', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $conversation = $this->module()?->getConversationManager()->continueConversation($conversationId);
        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        return $this->json([
            'success' => true,
            'conversation' => $conversation->toArray(),
        ]);
    }
}
