<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use yii\base\Component;

class ExecutionJournal extends Component
{
    public function start(ToolDefinition $definition, ToolExecutionContext $context, array $arguments, array $metadata = []): ?\eseperio\aiagent\models\Execution
    {
        $class = $this->getModule()?->executionClass;
        if (!$class || !$this->tableExists($class::tableName())) {
            return null;
        }

        $execution = new $class();
        $execution->conversation_id = $context->conversation?->id;
        $execution->snapshot_id = $context->toolSnapshot['id'] ?? null;
        $execution->response_id = $context->responseId;
        $execution->tool_call_id = $context->toolCallId;
        $execution->tool_name = $definition->name;
        $execution->actor_id = $this->resolveActorId($context->user);
        $execution->status = 'running';
        $execution->effect = (string)($metadata['effect'] ?? ($definition->metadata['effect'] ?? 'write'));
        $execution->risk_level = (string)($metadata['riskLevel'] ?? ($definition->metadata['riskLevel'] ?? 'medium'));
        $execution->resource_type = $definition->metadata['resourceType'] ?? null;
        $execution->idempotency_key = $arguments['idempotency_key'] ?? null;
        $execution->expected_version = $arguments['expected_version'] ?? null;
        $execution->arguments_json = json_encode($this->redactArguments($arguments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $execution->started_at = time();
        $execution->save(false);

        return $execution;
    }

    public function finish(?\eseperio\aiagent\models\Execution $execution, ToolResult $result): void
    {
        if ($execution === null) {
            return;
        }

        $execution->status = $result->success ? 'succeeded' : 'failed';
        $execution->result_json = json_encode([
            'success' => $result->success,
            'data' => $result->data,
            'error' => $result->error,
            'message' => $result->message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $execution->error_json = $result->success ? null : json_encode(['error' => $result->error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $execution->finished_at = time();
        $execution->save(false);
    }

    private function redactArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (preg_match('/token|secret|password|key/i', (string)$key)) {
                $arguments[$key] = '[redacted]';
            }
        }

        return $arguments;
    }

    private function resolveActorId(mixed $user): ?string
    {
        if (is_object($user) && method_exists($user, 'getId')) {
            $id = $user->getId();
            return $id === null ? null : (string)$id;
        }

        return is_scalar($user) ? (string)$user : null;
    }

    private function tableExists(string $table): bool
    {
        try {
            return \Yii::$app->db->schema->getTableSchema($table, true) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
