<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\PermissionContext;
use yii\base\Component;

class PermissionChecker extends Component
{
    public function can(string $permission, PermissionContext $context): bool
    {
        $module = $this->getModule();
        $rule = $module?->permissions[$permission] ?? null;

        if (is_bool($rule)) {
            return $rule;
        }

        if (is_callable($rule)) {
            return (bool)call_user_func($rule, $context);
        }

        return $this->defaultDecision($permission, $context);
    }

    public function canViewChat(PermissionContext $context): bool { return $this->can('canViewChat', $context); }
    public function canCreateChat(PermissionContext $context): bool { return $this->can('canCreateChat', $context); }
    public function canViewHistory(PermissionContext $context): bool { return $this->can('canViewHistory', $context); }
    public function canContinueChat(PermissionContext $context): bool { return $this->can('canContinueChat', $context); }
    public function canSendMessage(PermissionContext $context): bool { return $this->can('canSendMessage', $context); }
    public function canRenameChat(PermissionContext $context): bool { return $this->can('canRenameChat', $context); }
    public function canDeleteChat(PermissionContext $context): bool { return $this->can('canDeleteChat', $context); }
    public function canArchiveChat(PermissionContext $context): bool { return $this->can('canArchiveChat', $context); }
    public function canSetContext(PermissionContext $context): bool { return $this->can('canSetContext', $context); }
    public function canExecuteTool(PermissionContext $context): bool { return $this->can('canExecuteTool', $context); }
    public function canRenderContext(PermissionContext $context): bool { return $this->can('canRenderContext', $context); }
    public function canUseModel(PermissionContext $context): bool { return $this->can('canUseModel', $context); }

    private function defaultDecision(string $permission, PermissionContext $context): bool
    {
        if (str_starts_with($permission, 'canView') || $permission === 'canRenderContext') {
            return true;
        }

        $user = $context->user ?? (\Yii::$app->has('user') ? \Yii::$app->user->identity : null);
        return $user !== null;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
