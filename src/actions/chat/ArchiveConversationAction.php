<?php

namespace eseperio\aiagent\actions\chat;

class ArchiveConversationAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->post('conversation_id', 0);
        if (!$this->can('canArchiveChat', $this->permissionContext('archiveConversation', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $ok = $this->module()?->getConversationManager()->archiveConversation($conversationId) ?? false;
        return $this->json(['success' => $ok]);
    }
}
