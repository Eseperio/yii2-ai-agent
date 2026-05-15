<?php

namespace eseperio\aiagent\controllers;

use yii\web\NotFoundHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;

class ChatController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'get-history' => ['GET'],
                    'conversations' => ['GET'],
                    'list-conversations' => ['GET'],
                    'list-contexts' => ['GET'],
                    'create-conversation' => ['POST'],
                    'new-conversation' => ['POST'],
                    'continue-conversation' => ['POST'],
                    'set-conversation-context' => ['POST'],
                    'add-context' => ['POST'],
                    'remove-context' => ['POST'],
                    'render-contexts' => ['GET', 'POST'],
                    'send-message' => ['POST'],
                    'process-audio' => ['POST'],
                    'upload-asset' => ['POST'],
                    'asset' => ['GET'],
                    'execute-action' => ['POST'],
                    'execute-tool' => ['POST'],
                    'rename-conversation' => ['POST'],
                    'archive-conversation' => ['POST'],
                    'delete-conversation' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        $module = $this->module;
        $hasAppEnabledFlag = \Yii::$app && \Yii::$app->hasProperty('enableAiAssistant');
        $enabled = $hasAppEnabledFlag ? (bool)\Yii::$app->enableAiAssistant : ($module instanceof \eseperio\aiagent\Module && $module->enabled);

        if (!$module instanceof \eseperio\aiagent\Module || !$enabled) {
            throw new NotFoundHttpException();
        }

        return parent::beforeAction($action);
    }

    public function actions(): array
    {
        return [
            'index' => \eseperio\aiagent\actions\chat\IndexAction::class,
            'get-history' => \eseperio\aiagent\actions\chat\GetHistoryAction::class,
            'conversations' => \eseperio\aiagent\actions\chat\ListConversationsAction::class,
            'list-conversations' => \eseperio\aiagent\actions\chat\ListConversationsAction::class,
            'new-conversation' => \eseperio\aiagent\actions\chat\CreateConversationAction::class,
            'create-conversation' => \eseperio\aiagent\actions\chat\CreateConversationAction::class,
            'continue-conversation' => \eseperio\aiagent\actions\chat\ContinueConversationAction::class,
            'set-conversation-context' => \eseperio\aiagent\actions\chat\AddContextAction::class,
            'execute-action' => \eseperio\aiagent\actions\chat\ExecuteToolAction::class,
            'rename-conversation' => \eseperio\aiagent\actions\chat\RenameConversationAction::class,
            'archive-conversation' => \eseperio\aiagent\actions\chat\ArchiveConversationAction::class,
            'delete-conversation' => \eseperio\aiagent\actions\chat\DeleteConversationAction::class,
            'send-message' => \eseperio\aiagent\actions\chat\SendMessageAction::class,
            'process-audio' => \eseperio\aiagent\actions\chat\ProcessAudioAction::class,
            'upload-asset' => \eseperio\aiagent\actions\chat\UploadAssetAction::class,
            'asset' => \eseperio\aiagent\actions\chat\AssetAction::class,
            'execute-tool' => \eseperio\aiagent\actions\chat\ExecuteToolAction::class,
            'list-contexts' => \eseperio\aiagent\actions\chat\ListContextsAction::class,
            'add-context' => \eseperio\aiagent\actions\chat\AddContextAction::class,
            'remove-context' => \eseperio\aiagent\actions\chat\RemoveContextAction::class,
            'render-contexts' => \eseperio\aiagent\actions\chat\RenderContextsAction::class,
        ];
    }
}
