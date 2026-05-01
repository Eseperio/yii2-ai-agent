<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\models\ToolSnapshot;
use yii\base\Component;

class ToolSnapshotRepository extends Component
{
    public function save(int $conversationId, ?string $responseId, ToolDefinition $tool, array $contextFingerprint = [], ?string $requestId = null): ToolSnapshot
    {
        $contextRenderer = new ContextRenderer();
        $snapshot = new ToolSnapshot();
        $snapshot->conversation_id = $conversationId;
        $snapshot->response_id = $responseId;
        $snapshot->tool_name = $tool->name;
        $snapshot->provider_id = $tool->providerId;
        $snapshot->context_fingerprint = $contextFingerprint ? $contextRenderer->resolveFingerprint($contextFingerprint) : null;
        $snapshot->definition_json = json_encode([
            'name' => $tool->name,
            'description' => $tool->description,
            'parameters' => $tool->parameters,
            'requiresApproval' => $tool->requiresApproval,
            'providerId' => $tool->providerId,
            'contextTypes' => $tool->contextTypes,
            'metadata' => $tool->metadata,
            'request_id' => $requestId,
        ]);
        $snapshot->created_at = time();
        $snapshot->save(false);

        return $snapshot;
    }

    public function findOneByResponseAndTool(int $conversationId, ?string $responseId, string $toolName): ?ToolSnapshot
    {
        return ToolSnapshot::find()
            ->where([
                'conversation_id' => $conversationId,
                'response_id' => $responseId,
                'tool_name' => $toolName,
            ])
            ->one();
    }

    public function findOneByConversationAndTool(int $conversationId, string $toolName): ?ToolSnapshot
    {
        return ToolSnapshot::find()
            ->where([
                'conversation_id' => $conversationId,
                'tool_name' => $toolName,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function attachResponseIdByRequestId(int $conversationId, string $requestId, ?string $responseId): int
    {
        $snapshots = ToolSnapshot::find()
            ->where(['conversation_id' => $conversationId])
            ->all();

        $count = 0;
        foreach ($snapshots as $snapshot) {
            $data = json_decode((string)$snapshot->definition_json, true);
            if (($data['request_id'] ?? null) !== $requestId) {
                continue;
            }
            $snapshot->response_id = $responseId;
            $snapshot->save(false, ['response_id']);
            $count++;
        }

        return $count;
    }

    public function toDefinition(ToolSnapshot $snapshot): ToolDefinition
    {
        $data = json_decode((string)$snapshot->definition_json, true);
        return new ToolDefinition(
            $data['name'] ?? $snapshot->tool_name,
            $data['description'] ?? $snapshot->tool_name,
            $data['parameters'] ?? ['type' => 'object', 'properties' => []],
            null,
            (bool)($data['requiresApproval'] ?? false),
            $data['providerId'] ?? $snapshot->provider_id,
            $data['contextTypes'] ?? [],
            null,
            $data['metadata'] ?? []
        );
    }
}
