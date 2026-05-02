<?php

namespace eseperio\aiagent\actions\chat;

class AddContextAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        $type = (int)$request->post('type', 0);
        if ($conversationId <= 0 || $type <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        $conversation = $this->module()?->getConversationManager()->getConversation($conversationId);
        if (!$conversation || $conversation->status === 'deleted') {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        if (!$this->can('canSetContext', $this->permissionContext('addContext', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $context = $this->module()?->getContextManager()->addContext(
            $conversationId,
            $type,
            is_array($request->post('metadata')) ? $request->post('metadata') : [],
            $request->post('label')
        );

        return $this->json([
            'success' => (bool)$context,
            'context' => $context ? $context->toArray() : null,
        ]);
    }
}
