<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require dirname(__DIR__, 4) . '/vendor/yiisoft/yii2/Yii.php';

$srcBase = dirname(__DIR__, 3) . '/src/';
spl_autoload_register(static function (string $class) use ($srcBase): void {
    $prefix = 'eseperio\\aiagent\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $srcBase . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$testsBase = dirname(__DIR__, 3) . '/tests/';
spl_autoload_register(static function (string $class) use ($testsBase): void {
    $prefix = 'eseperio\\aiagent\\tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $testsBase . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$config = require dirname(__DIR__) . '/config.php';

$app = new \yii\web\Application($config);
$db = $app->db;
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
$route = $_GET['r'] ?? null;
if (is_string($route) && $route !== '') {
    try {
        $result = $app->runAction($route);
        $statusCode = $app->response->statusCode ?? 200;
        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }
        if (is_string($result)) {
            echo $result;
        } elseif (is_array($result)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($result);
        }
        return;
    } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'error' => $e->getMessage(),
            'class' => get_class($e),
        ]);
        return;
    }
}

$app->run();
