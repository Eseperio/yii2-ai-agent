<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';

\yii\helpers\FileHelper::createDirectory(sys_get_temp_dir() . '/yii2-ai-agent-assets');

spl_autoload_register(static function (string $class): void {
    $prefix = 'eseperio\\aiagent\\';
    $baseDir = dirname(__DIR__) . '/src/';
    $testsBaseDir = dirname(__DIR__) . '/tests/';

    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative) . '.php';
        $srcPath = $baseDir . $relativePath;
        if (is_file($srcPath)) {
            require_once $srcPath;
            return;
        }

        $testsPath = $testsBaseDir . $relativePath;
        if (is_file($testsPath)) {
            require_once $testsPath;
        }
    }
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'eseperio\\aiagent\\tests\\';
    $baseDir = dirname(__DIR__) . '/tests/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

new \yii\console\Application([
    'id' => 'yii2-ai-agent-tests',
    'basePath' => dirname(__DIR__),
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'sqlite::memory:',
        ],
    ],
]);

$db = \Yii::$app->db;
$db->createCommand()->createTable('{{%ai_agent_conversation}}', [
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
], null)->execute();
$db->createCommand()->createTable('{{%ai_agent_context}}', [
    'id' => 'pk',
    'conversation_id' => 'integer not null',
    'type' => 'integer not null',
    'metadata' => 'text not null',
    'label' => 'varchar(255) null',
    'status' => 'varchar(20) not null default "active"',
    'sort_order' => 'integer not null default 0',
    'created_at' => 'integer not null',
    'updated_at' => 'integer not null',
], null)->execute();
$db->createCommand()->createTable('{{%ai_agent_message}}', [
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
], null)->execute();
$db->createCommand()->createTable('{{%ai_agent_tool_snapshot}}', [
    'id' => 'pk',
    'conversation_id' => 'integer not null',
    'response_id' => 'varchar(255) null',
    'tool_name' => 'varchar(128) not null',
    'provider_id' => 'varchar(128) null',
    'context_fingerprint' => 'varchar(64) null',
    'definition_json' => 'text not null',
    'created_at' => 'integer not null',
    'updated_at' => 'integer not null',
], null)->execute();
