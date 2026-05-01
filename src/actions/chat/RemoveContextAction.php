<?php

namespace eseperio\aiagent\actions\chat;

class RemoveContextAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        $contextId = (int)$request->post('context_id', $request->post('id', 0));
        if ($conversationId <= 0 || $contextId <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        if (!$this->can('canSetContext', $this->permissionContext('removeContext', ['conversation_id' => $conversationId, 'context_id' => $contextId]))) {
            return $this->deny();
        }

        $hardDelete = (bool)$request->post('hard_delete', false);
        $ok = $hardDelete
            ? ($this->module()?->getContextManager()->deleteContext($contextId) ?? false)
            : ($this->module()?->getContextManager()->archiveContext($contextId) ?? false);

        return $this->json(['success' => $ok]);
    }
}
