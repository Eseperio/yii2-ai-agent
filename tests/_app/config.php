<?php

$dbPath = sys_get_temp_dir() . '/yii2-ai-agent-tests.sqlite';

return [
    'id' => 'yii2-ai-agent-tests',
    'basePath' => dirname(__DIR__),
    'defaultRoute' => 'aiAgent/chat/index',
    'vendorPath' => dirname(__DIR__, 3) . '/vendor',
    'bootstrap' => [],
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'sqlite:' . $dbPath,
        ],
        'request' => [
            'cookieValidationKey' => 'test-key',
            'enableCsrfValidation' => false,
            'scriptUrl' => '/index.php',
            'baseUrl' => '',
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
        ],
        'assetManager' => [
            'basePath' => sys_get_temp_dir() . '/yii2-ai-agent-assets',
            'baseUrl' => '/assets',
        ],
        'user' => [
            'identityClass' => \eseperio\aiagent\tests\DummyIdentity::class,
            'enableSession' => false,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET ai-agent/chat/<action>' => 'aiAgent/chat/<action>',
                'POST ai-agent/chat/<action>' => 'aiAgent/chat/<action>',
                'POST fake-openai/v1/responses' => 'fake-openai/v1/responses',
            ],
        ],
    ],
    'modules' => [
        'aiAgent' => [
            'class' => \eseperio\aiagent\Module::class,
            'clientConfig' => ['apiKey' => 'test'],
            'autoExecutionMaxIterations' => 3,
            'permissions' => [
                'canViewChat' => true,
                'canCreateChat' => true,
                'canViewHistory' => true,
                'canContinueChat' => true,
                'canSendMessage' => true,
                'canRenameChat' => true,
                'canDeleteChat' => true,
                'canArchiveChat' => true,
                'canSetContext' => true,
                'canExecuteTool' => true,
                'canRenderContext' => static function ($context): bool {
                    return empty($context->request?->post('deny_render_context'));
                },
                'canUseModel' => static function ($context): bool {
                    return empty($context->request?->get('deny_model'));
                },
            ],
            'tools' => [
                new \eseperio\aiagent\dto\ToolDefinition(
                    'demo_tool',
                    'Demo tool',
                    ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
                    function ($context) {
                        $contextCount = is_array($context->contexts ?? null) ? count($context->contexts) : 0;
                        return new \eseperio\aiagent\dto\ToolResult(
                            true,
                            ['ok' => true, 'context_count' => $contextCount],
                            null,
                            [
                                [
                                    'type' => 42,
                                    'label' => 'Created from tool',
                                    'metadata' => ['class' => 'Demo', 'id' => 99],
                                ],
                            ],
                            [],
                            'done'
                        );
                    },
                    true,
                    'demo',
                    [],
                    null,
                    ['scope' => 'test']
                ),
                new \eseperio\aiagent\dto\ToolDefinition(
                    'auto_demo_tool',
                    'Auto demo tool',
                    ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
                    function ($context) {
                        return new \eseperio\aiagent\dto\ToolResult(true, ['auto' => true], null, [], [], 'auto done');
                    },
                    false,
                    'auto-demo',
                    [],
                    null,
                    ['scope' => 'test']
                ),
                new \eseperio\aiagent\dto\ToolDefinition(
                    'auto_demo_tool_many',
                    'Auto demo tool many',
                    ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
                    function ($context) {
                        return new \eseperio\aiagent\dto\ToolResult(true, ['auto' => true], null, [], [], 'auto done');
                    },
                    false,
                    'auto-demo-many',
                    [],
                    null,
                    ['scope' => 'test']
                ),
                new \eseperio\aiagent\dto\ToolDefinition(
                    'class_demo_tool',
                    'Class demo tool',
                    ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
                    \eseperio\aiagent\tests\handlers\ClassDemoToolHandler::class,
                    true,
                    'class-demo',
                    [],
                    null,
                    ['scope' => 'test']
                ),
            ],
        ],
        'fake-openai' => [
            'class' => \eseperio\aiagent\modules\FakeOpenAiModule::class,
        ],
    ],
];
