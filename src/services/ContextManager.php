<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\models\Context;
use yii\base\Component;

class ContextManager extends Component
{
    public function listContexts(int $conversationId): array
    {
        $class = $this->contextClass();
        return $class::find()
            ->where(['conversation_id' => $conversationId, 'status' => 'active'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    public function addContext(int $conversationId, int $type, array $metadata, ?string $label = null, int $sortOrder = 0): Context
    {
        $class = $this->contextClass();
        $context = new $class();
        $context->conversation_id = $conversationId;
        $context->type = $type;
        $context->metadata = json_encode($metadata);
        $context->label = $label;
        $context->sort_order = $sortOrder;
        $context->status = 'active';
        $context->save(false);

        return $context;
    }

    public function updateContext(int $contextId, ?int $type = null, ?array $metadata = null, ?string $label = null, ?int $sortOrder = null): ?Context
    {
        $class = $this->contextClass();
        $context = $class::findOne($contextId);
        if (!$context) {
            return null;
        }
        if ($type !== null && $type > 0) {
            $context->type = $type;
        }
        if ($metadata !== null) {
            $context->metadata = json_encode($metadata);
        }
        if ($label !== null) {
            $context->label = $label;
        }
        if ($sortOrder !== null) {
            $context->sort_order = $sortOrder;
        }
        $context->save(false);

        return $context;
    }

    public function archiveContext(int $contextId): bool
    {
        return $this->setStatus($contextId, 'archived');
    }

    public function deleteContext(int $contextId): bool
    {
        return $this->setStatus($contextId, 'deleted');
    }

    private function setStatus(int $contextId, string $status): bool
    {
        $class = $this->contextClass();
        $context = $class::findOne($contextId);
        if (!$context) {
            return false;
        }
        $context->status = $status;
        return $context->save(false, ['status', 'updated_at']);
    }

    private function contextClass(): string
    {
        return \eseperio\aiagent\Module::resolveActive()?->contextClass ?: Context::class;
    }
}
