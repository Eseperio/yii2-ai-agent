<?php

namespace eseperio\aiagent\actions\chat;

class DeleteConversationAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->post('conversation_id', 0);
        if (!$this->can('canDeleteChat', $this->permissionContext('deleteConversation', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $ok = $this->module()?->getConversationManager()->deleteConversation($conversationId) ?? false;
        return $this->json(['success' => $ok]);
    }
}
