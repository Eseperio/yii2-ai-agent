<?php

namespace eseperio\aiagent\actions\chat;

class ListConversationsAction extends BaseChatAction
{
    public function run()
    {
        $permissionContext = $this->permissionContext('listConversations');
        if (!$this->can('canViewHistory', $permissionContext)) {
            return $this->deny();
        }

        $conversations = $this->module()?->getConversationManager()->listConversations() ?? [];
        return $this->json([
            'success' => true,
            'conversations' => array_map(static fn($conversation) => $conversation->toArray(), $conversations),
        ]);
    }
}
