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
        return $this->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }
}
