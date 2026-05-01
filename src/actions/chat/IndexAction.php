<?php

namespace eseperio\aiagent\actions\chat;

class IndexAction extends BaseChatAction
{
    public function run()
    {
        if (!$this->can('canViewChat', $this->permissionContext('index'))) {
            return $this->deny();
        }

        $requestedModel = $this->request()->get('model');
        if (is_string($requestedModel) && trim($requestedModel) !== '') {
            if (!$this->can('canUseModel', $this->permissionContext('index', ['model' => $requestedModel]))) {
                return $this->deny('Model not allowed');
            }
        }

        return \eseperio\aiagent\widgets\AiChat::widget([
            'mode' => $this->request()->get('mode', \eseperio\aiagent\widgets\AiChat::MODE_PAGE),
            'position' => $this->request()->get('position', \eseperio\aiagent\widgets\AiChat::POSITION_BOTTOM_RIGHT),
            'model' => $requestedModel,
            'conversationId' => (int)$this->request()->get('conversation_id', 0) ?: null,
        ]);
    }
}
