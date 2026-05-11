<?php

namespace eseperio\aiagent\widgets;

use eseperio\aiagent\dto\PermissionContext;
use eseperio\aiagent\assets\AiChatAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\base\Widget;

class AiChat extends Widget
{
    public const MODE_FLOATING = 'floating';
    public const MODE_PAGE = 'page';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_TOP_LEFT = 'top-left';

    public string $mode = self::MODE_FLOATING;
    public string $position = self::POSITION_BOTTOM_RIGHT;
    public ?string $model = null;
    public array $apiUrls = [];
    public array $contexts = [];
    public ?int $conversationId = null;
    public bool $autoOpen = false;
    public bool $showConversationList = true;
    public string $conversationUrlParam = 'conversation_id';
    public array $htmlOptions = [];
    public ?string $toolsExecutedCallback = null;

    public function run(): string
    {
        $module = $this->getModule();
        $hasAppEnabledFlag = \Yii::$app->hasProperty('enableAiAssistant');
        $enabled = $hasAppEnabledFlag ? (bool)\Yii::$app->enableAiAssistant : $module?->enabled;
        if (!$module || !$enabled) {
            return '';
        }

        if (!$module->getPermissionChecker()->canViewChat(new PermissionContext(action: 'widget', user: $this->getViewUser()))) {
            return '';
        }

        try {
            $assetManager = \Yii::$app->has('assetManager') ? \Yii::$app->assetManager : null;
            if ($assetManager && is_string($assetManager->basePath) && $assetManager->basePath !== '' && !is_dir($assetManager->basePath)) {
                FileHelper::createDirectory($assetManager->basePath);
            }
            AiChatAsset::register($this->getView());
        } catch (\yii\base\InvalidConfigException) {
            // Some functional test apps render the widget without a webroot. The mount remains usable for assertions.
        }

        $options = $this->htmlOptions;
        $options['class'] = trim(($options['class'] ?? '') . ' ai-agent-widget ai-assistant-mount');
        $options['data'] = array_merge($options['data'] ?? [], [
            'props' => Json::htmlEncode($this->buildProps($module)),
        ]);

        return Html::tag('div', '', $options);
    }

    protected function buildProps(\eseperio\aiagent\Module $module): array
    {
        $conversationModel = null;
        if ($this->conversationId !== null) {
            $conversation = $module->getConversationManager()->getConversation($this->conversationId);
            $conversationModel = $conversation?->model;
        }

        return [
            'mode' => $this->mode,
            'position' => $this->mode === self::MODE_FLOATING ? $this->position : null,
            'model' => $module->resolveModel($this->model, $conversationModel),
            'conversationId' => $this->conversationId,
            'contexts' => $this->contexts,
            'apiUrls' => $this->resolveApiUrls(),
            'autoOpen' => $this->autoOpen,
            'showConversationList' => $this->showConversationList,
            'conversationUrlParam' => $this->conversationUrlParam,
            'toolsExecutedCallback' => $this->toolsExecutedCallback,
            'welcomeMessages' => array_values(array_filter(
                $module->welcomeMessages,
                static fn($message): bool => is_string($message) && trim($message) !== ''
            )),
            'permissions' => [
                'canViewChat' => true,
                'canCreateChat' => $module->getPermissionChecker()->canCreateChat(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canViewHistory' => $module->getPermissionChecker()->canViewHistory(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canContinueChat' => $module->getPermissionChecker()->canContinueChat(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canSendMessage' => $module->getPermissionChecker()->canSendMessage(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canRenameChat' => $module->getPermissionChecker()->canRenameChat(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canDeleteChat' => $module->getPermissionChecker()->canDeleteChat(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canArchiveChat' => $module->getPermissionChecker()->canArchiveChat(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canSetContext' => $module->getPermissionChecker()->canSetContext(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canExecuteTool' => $module->getPermissionChecker()->canExecuteTool(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canRenderContext' => $module->getPermissionChecker()->canRenderContext(new PermissionContext(action: 'widget', user: $this->getViewUser())),
                'canUseModel' => $module->getPermissionChecker()->canUseModel(new PermissionContext(action: 'widget', user: $this->getViewUser(), model: $this->model)),
            ],
        ];
    }

    protected function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }

    protected function getViewUser(): mixed
    {
        return \Yii::$app->has('user') ? \Yii::$app->user->identity : null;
    }

    protected function defaultApiUrls(): array
    {
        return [
            'getHistory' => $this->routeUrl('get-history'),
            'listConversations' => $this->routeUrl('list-conversations'),
            'createConversation' => $this->routeUrl('create-conversation'),
            'renameConversation' => $this->routeUrl('rename-conversation'),
            'archiveConversation' => $this->routeUrl('archive-conversation'),
            'deleteConversation' => $this->routeUrl('delete-conversation'),
            'sendMessage' => $this->routeUrl('send-message'),
            'uploadAsset' => $this->routeUrl('upload-asset'),
            'executeTool' => $this->routeUrl('execute-tool'),
            'renderContexts' => $this->routeUrl('render-contexts'),
        ];
    }

    private function resolveApiUrls(): array
    {
        if ($this->apiUrls === []) {
            return $this->defaultApiUrls();
        }

        return array_is_list($this->apiUrls)
            ? $this->apiUrls
            : array_merge($this->defaultApiUrls(), $this->apiUrls);
    }

    private function routeUrl(string $action): string
    {
        $route = '/' . $this->moduleRoute() . '/chat/' . $action;
        try {
            return Url::to([$route]);
        } catch (\Throwable) {
            return '/index.php?r=' . ltrim($route, '/');
        }
    }

    private function moduleRoute(): string
    {
        return $this->getModule()?->id ?? 'aiAgent';
    }
}
