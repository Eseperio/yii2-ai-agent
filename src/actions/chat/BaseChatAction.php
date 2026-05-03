<?php

namespace eseperio\aiagent\actions\chat;

use eseperio\aiagent\dto\PermissionContext;
use eseperio\aiagent\dto\ContextRenderContext;
use eseperio\aiagent\models\Context;
use yii\base\Action;
use yii\web\Response;

abstract class BaseChatAction extends Action
{
    protected function json(array $data, int $statusCode = 200): array
    {
        if (\Yii::$app->has('response')) {
            \Yii::$app->response->statusCode = $statusCode;
            \Yii::$app->response->format = Response::FORMAT_JSON;
        }

        return $data;
    }

    protected function request(): \yii\web\Request
    {
        return \Yii::$app->request;
    }

    protected function module(): ?\eseperio\aiagent\Module
    {
        $module = $this->controller?->module;
        if ($module instanceof \eseperio\aiagent\Module) {
            return $module;
        }

        return \eseperio\aiagent\Module::resolveActive();
    }

    protected function user(): mixed
    {
        return \Yii::$app->has('user') ? \Yii::$app->user->identity : null;
    }

    protected function deny(string $message = 'Forbidden', int $statusCode = 403): array
    {
        return $this->json([
            'success' => false,
            'error' => $message,
        ], $statusCode);
    }

    protected function permissionContext(string $action, array $metadata = []): PermissionContext
    {
        return new PermissionContext(
            action: $action,
            user: $this->user(),
            request: $this->request(),
            metadata: $metadata
        );
    }

    protected function can(string $method, PermissionContext $context): bool
    {
        return $this->module()?->getPermissionChecker()->{$method}($context) ?? false;
    }

    protected function addContextPreviewMessage(int $conversationId, Context $context, ?string $responseId = null): void
    {
        $module = $this->module();
        $manager = $module?->getConversationManager();
        if (!$module || !$manager) {
            return;
        }

        $conversation = $manager->getConversation($conversationId);
        if (!$conversation) {
            return;
        }

        $manager->addMessage(
            $conversationId,
            'assistant',
            'context',
            json_encode($this->renderContextPreviewPayload($conversationId, $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $responseId
        );
    }

    protected function renderContextPreviewPayload(int $conversationId, Context $context): array
    {
        $module = $this->module();
        $conversation = $module?->getConversationManager()->getConversation($conversationId);
        if (!$module || !$conversation) {
            return [
                'type' => $context->type,
                'id' => $context->id,
                'title' => $context->label ?: 'Context #' . $context->id,
            ];
        }

        $renderContext = new ContextRenderContext(
            conversation: $conversation,
            user: $this->user(),
            request: $this->request(),
            metadata: [
                'conversation_id' => $conversationId,
                'source' => 'context_preview',
            ]
        );

        return $module->getContextRenderer()->render($context, $renderContext);
    }
}
