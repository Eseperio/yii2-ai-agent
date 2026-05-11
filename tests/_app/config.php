<?php

$dbPath = sys_get_temp_dir() . '/yii2-ai-agent-tests.sqlite';

return [
    'id' => 'yii2-ai-agent-tests',
    'basePath' => dirname(__DIR__),
    'defaultRoute' => 'aiAgent/chat/index',
    'vendorPath' => dirname(__DIR__, 3) . '/vendor',
    'bootstrap' => ['aiAgent'],
    'on beforeRequest' => static function (): void {
        $db = \Yii::$app->db;
        foreach ([
            '{{%ai_agent_conversation}}' => [
                'id' => 'pk',
                'title' => 'varchar(255) null',
                'status' => 'varchar(20) not null default "active"',
                'model' => 'varchar(100) null',
                'created_by' => 'varchar(64) null',
                'metadata' => 'text null',
                'last_response_id' => 'varchar(255) null',
                'last_message_at' => 'integer null',
                'input_tokens_total' => 'integer not null default 0',
                'output_tokens_total' => 'integer not null default 0',
                'total_tokens_total' => 'integer not null default 0',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_context}}' => [
                'id' => 'pk',
                'conversation_id' => 'integer not null',
                'type' => 'integer not null',
                'metadata' => 'text not null',
                'label' => 'varchar(255) null',
                'status' => 'varchar(20) not null default "active"',
                'sort_order' => 'integer not null default 0',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_message}}' => [
                'id' => 'pk',
                'conversation_id' => 'integer not null',
                'role' => 'varchar(20) not null',
                'message_type' => 'varchar(30) not null',
                'content' => 'text not null',
                'response_id' => 'varchar(255) null',
                'tool_call_id' => 'varchar(255) null',
                'tool_name' => 'varchar(128) null',
                'tool_payload' => 'text null',
                'input_tokens' => 'integer not null default 0',
                'output_tokens' => 'integer not null default 0',
                'total_tokens' => 'integer not null default 0',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_asset}}' => [
                'id' => 'pk',
                'type' => 'varchar(20) not null',
                'source' => 'varchar(20) not null',
                'status' => 'varchar(20) not null default "ready"',
                'mime_type' => 'varchar(128) not null',
                'filename' => 'varchar(255) not null',
                'storage_path' => 'varchar(512) not null',
                'public_token' => 'varchar(64) not null',
                'size' => 'integer null',
                'width' => 'integer null',
                'height' => 'integer null',
                'hash' => 'varchar(64) null',
                'metadata' => 'text null',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_message_asset}}' => [
                'id' => 'pk',
                'message_id' => 'integer not null',
                'asset_id' => 'integer not null',
                'usage' => 'varchar(20) not null default "input"',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_asset_target}}' => [
                'id' => 'pk',
                'asset_id' => 'integer not null',
                'target_type' => 'varchar(128) not null',
                'target_id' => 'varchar(128) not null',
                'handler' => 'varchar(128) null',
                'result' => 'text null',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_tool_snapshot}}' => [
                'id' => 'pk',
                'conversation_id' => 'integer not null',
                'response_id' => 'varchar(255) null',
                'tool_name' => 'varchar(128) not null',
                'provider_id' => 'varchar(128) null',
                'context_fingerprint' => 'varchar(64) null',
                'definition_json' => 'text not null',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
            '{{%ai_agent_execution}}' => [
                'id' => 'pk',
                'conversation_id' => 'integer null',
                'snapshot_id' => 'integer null',
                'response_id' => 'varchar(255) null',
                'tool_call_id' => 'varchar(255) null',
                'tool_name' => 'varchar(128) not null',
                'actor_id' => 'varchar(64) null',
                'status' => 'varchar(30) not null default "running"',
                'effect' => 'varchar(30) not null default "write"',
                'risk_level' => 'varchar(30) not null default "medium"',
                'resource_type' => 'varchar(128) null',
                'resource_id' => 'varchar(128) null',
                'idempotency_key' => 'varchar(128) null',
                'expected_version' => 'varchar(64) null',
                'observed_version' => 'varchar(64) null',
                'arguments_json' => 'text null',
                'result_json' => 'text null',
                'error_json' => 'text null',
                'started_at' => 'integer null',
                'finished_at' => 'integer null',
                'created_at' => 'integer not null',
                'updated_at' => 'integer not null',
            ],
        ] as $table => $columns) {
            if ($db->getTableSchema($table, true) === null) {
                $db->createCommand()->createTable($table, $columns, null)->execute();
            }
        }
    },
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
            'assetStorageConfig' => ['root' => sys_get_temp_dir() . '/yii2-ai-agent-chat-assets'],
            'autoExecutionMaxIterations' => 3,
            'mcpEnabled' => true,
            'mcpRoute' => 'mcp',
            'mcpIssuer' => 'http://localhost',
            'mcpAudience' => 'yii2-ai-agent-tests',
            'mcpJwtSecret' => 'mcp-test-secret',
            'mcpScopes' => ['test.read', 'test.write'],
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
                    ['scope' => 'test', 'mcp' => true, 'mcpScopes' => ['test.write'], 'effect' => 'read', 'riskLevel' => 'low', 'allowAutonomous' => true]
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
                    ['scope' => 'test', 'mcp' => true, 'mcpScopes' => ['test.read'], 'effect' => 'read', 'riskLevel' => 'low', 'allowAutonomous' => true]
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
                new \eseperio\aiagent\dto\ToolDefinition(
                    'blocked_delete_tool',
                    'Blocked delete tool',
                    ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']]],
                    function () {
                        return new \eseperio\aiagent\dto\ToolResult(true, ['deleted' => true], null, [], [], 'deleted');
                    },
                    true,
                    'blocked-delete',
                    [],
                    null,
                    ['scope' => 'test', 'effect' => 'delete', 'riskLevel' => 'high']
                ),
            ],
            'manuals' => [
                new \eseperio\aiagent\dto\ManualDefinition(
                    'test.product.manual',
                    'Product manual',
                    'How to create test products.',
                    'Ask for options, create product, create features, verify state.',
                    [],
                    null,
                    ['topic' => 'product']
                ),
            ],
        ],
        'fake-openai' => [
            'class' => \eseperio\aiagent\modules\FakeOpenAiModule::class,
        ],
    ],
];
