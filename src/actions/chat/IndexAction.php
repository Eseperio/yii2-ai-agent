<?php

namespace eseperio\aiagent\actions\chat;

use yii\web\Response;

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

        if (\Yii::$app->has('response')) {
            \Yii::$app->response->format = Response::FORMAT_HTML;
        }

        $content = \eseperio\aiagent\widgets\AiChat::widget([
            'mode' => $this->request()->get('mode', \eseperio\aiagent\widgets\AiChat::MODE_PAGE),
            'position' => $this->request()->get('position', \eseperio\aiagent\widgets\AiChat::POSITION_BOTTOM_RIGHT),
            'model' => $requestedModel,
            'conversationId' => (int)$this->request()->get('conversation_id', 0) ?: null,
        ]);

        return $this->controller->renderContent($content);
    }
}
