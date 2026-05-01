<?php

namespace eseperio\aiagent\actions\chat;

use eseperio\aiagent\dto\PermissionContext;
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
}
