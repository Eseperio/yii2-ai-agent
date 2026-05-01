<?php

namespace eseperio\aiagent\actions\chat;

class AddContextAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        if (!$this->can('canSetContext', $this->permissionContext('addContext', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }
        $context = $this->module()?->getContextManager()->addContext(
            $conversationId,
            (int)$request->post('type', 0),
            is_array($request->post('metadata')) ? $request->post('metadata') : [],
            $request->post('label')
        );

        return $this->json([
            'success' => (bool)$context,
            'context' => $context ? $context->toArray() : null,
        ]);
    }
}
