<?php

namespace eseperio\aiagent\actions\chat;

class RenameConversationAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        if (!$this->can('canRenameChat', $this->permissionContext('renameConversation', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $ok = $this->module()?->getConversationManager()->renameConversation(
            $conversationId,
            $request->post('title')
        ) ?? false;

        return $this->json(['success' => $ok]);
    }
}
