<?php

namespace eseperio\aiagent;

use eseperio\aiagent\services\AiClientFactory;
use eseperio\aiagent\services\ConversationManager;
use eseperio\aiagent\services\ContextManager;
use eseperio\aiagent\services\ContextRenderer;
use eseperio\aiagent\services\AiResponseService;
use eseperio\aiagent\services\ManualRegistry;
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
    public array $manuals = [];
    public array $manualProviders = [];
    public array $instructionProviders = [];
    public array $contextRenderers = [];
    public array $welcomeMessages = [
        'Hola, ¿qué hacemos hoy?',
        'Hola, ¿por dónde empezamos?',
        'Buenas, ¿qué idea traes hoy?',
        'Hola, cuéntame qué quieres construir.',
        'Buenas, dime qué necesitas y lo vemos.',
        'Hola, ¿en qué te ayudo hoy?',
        'Buenas, ¿qué quieres resolver?',
        'Hola, dime qué tienes en mente.',
        'Buenas, ¿qué preparamos hoy?',
        'Hola, ¿qué quieres mejorar?',
        'Buenas, cuéntame el objetivo.',
        'Hola, ¿qué tarea tenemos por delante?',
        'Buenas, ¿qué quieres crear?',
        'Hola, dime la idea y avanzamos.',
        'Buenas, ¿qué necesitas preparar?',
        'Hola, ¿qué quieres revisar?',
        'Buenas, ¿qué hacemos ahora?',
        'Hola, te escucho.',
        'Buenas, dime qué quieres conseguir.',
        'Hola, ¿qué plan tienes hoy?',
    ];
    public string $baseInstructions = <<<'TEXT'
You are an AI assistant embedded in a Yii2 application through the yii2-ai-agent module.

Interaction contract:
- Always return only the JSON object requested by the configured response format. Do not wrap it in Markdown and do not add text outside the JSON object.
- `response` is visible to the user in the chat. Keep it user-facing, concise, and free of internal protocol details.
- Never put selectable options, "reply A/B", numbered menus, raw JSON, tool names, tool arguments, tool ids, or internal markers in `response`.
- If you need the user to answer a question, choose between options, disambiguate, confirm missing data, or provide more details, use `questionnaire.enabled=true`.
- Put every user-facing question inside `questionnaire.questions`. Use `single_choice`, `multiple_choice`, or `text` as appropriate.
- When `questionnaire.enabled=true`, `response` should only introduce why more information is needed; the actual questions and options must be in `questionnaire`.
- If the user just answered a questionnaire, continue from those answers. If more information is still required, return a new questionnaire instead of asking in prose.
- If no user question is needed, set `questionnaire.enabled=false`, `title=""`, `description=""`, and `questions=[]`.

Tool contract:
- Use the available tools for actions, searches, reads, writes, context changes, and any application operation that should affect real data.
- Do not claim that a real action was completed unless a tool result confirms it.
- If a tool requires approval, the application will show it to the user as an action card. Keep `response` as a natural-language explanation of what is being proposed.
- For destructive, ambiguous, or high-impact operations, ask for clarification or confirmation through `questionnaire` unless the user has already clearly authorized the action.

Manual contract:
- The application may expose procedural manuals as tools. Use `list_agent_manuals` and `read_agent_manual` before complex workflows when you need to know the correct application-specific procedure.
- Manuals are internal guidance for planning. Do not copy long manual text to the user; apply it and ask the user only for missing business decisions through `questionnaire`.

Conversation contract:
- `conversation_title_suggestion` must always be present. Use a short useful title when the topic is clear, otherwise use an empty string.
- Preserve a professional tone and avoid exposing implementation details.
TEXT;
    public ?array $responseTextFormat = [
        'type' => 'json_schema',
        'name' => 'ai_agent_response',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['response', 'conversation_title_suggestion', 'questionnaire'],
            'properties' => [
                'response' => ['type' => 'string'],
                'conversation_title_suggestion' => ['type' => 'string'],
                'questionnaire' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['enabled', 'title', 'description', 'questions'],
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'questions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['id', 'label', 'type', 'required', 'options', 'placeholder'],
                                'properties' => [
                                    'id' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'enum' => ['text', 'single_choice', 'multiple_choice']],
                                    'required' => ['type' => 'boolean'],
                                    'placeholder' => ['type' => 'string'],
                                    'options' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'additionalProperties' => false,
                                            'required' => ['value', 'label'],
                                            'properties' => [
                                                'value' => ['type' => 'string'],
                                                'label' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
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
            'manualRegistry' => ['class' => ManualRegistry::class],
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

    public function getManualRegistry(): ManualRegistry
    {
        return $this->get('manualRegistry');
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

    public function resolveWelcomeMessage(?int $conversationId = null): string
    {
        $messages = array_values(array_filter(
            $this->welcomeMessages,
            static fn($message): bool => is_string($message) && trim($message) !== ''
        ));
        if ($messages === []) {
            return '';
        }

        $base = $conversationId !== null && $conversationId > 0 ? $conversationId - 1 : 0;
        return $messages[$base % count($messages)];
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
