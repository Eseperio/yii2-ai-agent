<?php

namespace eseperio\aiagent\actions\chat;

class CreateConversationAction extends BaseChatAction
{
    public function run()
    {
        $permissionContext = $this->permissionContext('createConversation');
        if (!$this->can('canCreateChat', $permissionContext)) {
            return $this->deny();
        }

        $request = $this->request();
        $model = $request->post('model');
        if (is_string($model) && trim($model) !== '') {
            if (!$this->can('canUseModel', $this->permissionContext('createConversation', ['model' => $model]))) {
                return $this->deny('Model not allowed');
            }
        }

        $conversation = $this->module()?->getConversationManager()->createConversation(
            $request->post('title'),
            $model,
            is_array($request->post('metadata')) ? $request->post('metadata') : [],
            is_array($request->post('contexts')) ? $request->post('contexts') : [],
            $this->resolveCreatedBy()
        );

        return $this->json([
            'success' => (bool)$conversation,
            'conversation' => $conversation ? $conversation->toArray() : null,
        ]);
    }

    private function resolveCreatedBy(): mixed
    {
        $resolver = $this->module()?->userIdResolver;
        if (is_callable($resolver)) {
            return call_user_func($resolver, $this->user(), $this->request());
        }

        $identity = $this->user();
        return is_object($identity) && isset($identity->id) ? $identity->id : null;
    }
}
