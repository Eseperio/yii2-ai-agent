<?php

namespace eseperio\aiagent;

use eseperio\aiagent\services\AiClientFactory;
use eseperio\aiagent\services\ConversationManager;
use eseperio\aiagent\services\ContextManager;
use eseperio\aiagent\services\ContextRenderer;
use eseperio\aiagent\services\AiResponseService;
use eseperio\aiagent\services\PermissionChecker;
use eseperio\aiagent\services\ResponseParser;
use eseperio\aiagent\services\ToolSnapshotRepository;
use eseperio\aiagent\services\ToolRegistry;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public $controllerNamespace = 'eseperio\\aiagent\\controllers';
    public string $defaultModel = 'gpt-5.2-2025-12-11';
    public array $clientConfig = [];
    public ?string $serviceTier = null;
    public bool $enabled = true;
    public array $permissions = [];
    public array $tools = [];
    public array $toolProviders = [];
    public array $instructionProviders = [];
    public array $contextRenderers = [];
    public int $autoExecutionMaxIterations = 8;
    public bool $reuseLastEmptyConversation = true;
    public $userIdResolver = null;
    public $conversationTitleResolver = null;
    public string $conversationClass = \eseperio\aiagent\models\Conversation::class;
    public string $messageClass = \eseperio\aiagent\models\Message::class;
    public string $contextClass = \eseperio\aiagent\models\Context::class;
    public string $toolSnapshotClass = \eseperio\aiagent\models\ToolSnapshot::class;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'permissionChecker' => ['class' => PermissionChecker::class],
            'toolRegistry' => ['class' => ToolRegistry::class],
            'contextManager' => ['class' => ContextManager::class],
            'contextRenderer' => ['class' => ContextRenderer::class],
            'conversationManager' => ['class' => ConversationManager::class],
            'responseParser' => ['class' => ResponseParser::class],
            'clientFactory' => ['class' => AiClientFactory::class],
            'aiResponseService' => ['class' => AiResponseService::class],
            'toolSnapshotRepository' => ['class' => ToolSnapshotRepository::class],
        ]);
    }

    public function getPermissionChecker(): PermissionChecker
    {
        return $this->get('permissionChecker');
    }

    public function getToolRegistry(): ToolRegistry
    {
        return $this->get('toolRegistry');
    }

    public function getContextManager(): ContextManager
    {
        return $this->get('contextManager');
    }

    public function getConversationManager(): ConversationManager
    {
        return $this->get('conversationManager');
    }

    public function getResponseParser(): ResponseParser
    {
        return $this->get('responseParser');
    }

    public function getClientFactory(): AiClientFactory
    {
        return $this->get('clientFactory');
    }

    public function getContextRenderer(): ContextRenderer
    {
        return $this->get('contextRenderer');
    }

    public function getAiResponseService(): AiResponseService
    {
        return $this->get('aiResponseService');
    }

    public function getToolSnapshotRepository(): ToolSnapshotRepository
    {
        return $this->get('toolSnapshotRepository');
    }

    public function resolveModel(?string $widgetModel = null, ?string $conversationModel = null): string
    {
        $widgetModel = is_string($widgetModel) ? trim($widgetModel) : '';
        if ($widgetModel !== '') {
            return $widgetModel;
        }

        $conversationModel = is_string($conversationModel) ? trim($conversationModel) : '';
        if ($conversationModel !== '') {
            return $conversationModel;
        }

        return $this->defaultModel;
    }

    public function getAutoExecutionMaxIterations(): int
    {
        return max(1, $this->autoExecutionMaxIterations);
    }

    public static function resolveActive(): ?self
    {
        $app = \Yii::$app;
        if (!$app) {
            return null;
        }

        foreach (['aiAgent', 'ai-agent'] as $id) {
            if ($app->hasModule($id)) {
                $module = $app->getModule($id);
                if ($module instanceof self) {
                    return $module;
                }
            }
        }

        foreach (array_keys($app->getModules()) as $id) {
            $module = $app->getModule($id);
            if ($module instanceof self) {
                return $module;
            }
        }

        return null;
    }
}
