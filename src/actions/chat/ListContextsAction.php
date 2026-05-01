<?php

namespace eseperio\aiagent\actions\chat;

class ListContextsAction extends BaseChatAction
{
    public function run()
    {
        $conversationId = (int)$this->request()->get('conversation_id', 0);
        if (!$this->can('canViewHistory', $this->permissionContext('listContexts', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $contexts = $this->module()?->getContextManager()->listContexts($conversationId) ?? [];
        return $this->json([
            'success' => true,
            'contexts' => array_map(static fn($context) => $context->toArray(), $contexts),
        ]);
    }
}
