<?php

namespace eseperio\aiagent\actions\chat;

use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\contracts\ToolHandlerInterface;

class ExecuteToolAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        $toolName = (string)$request->post('tool_name', '');
        if ($conversationId <= 0 || trim($toolName) === '') {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        if (!$this->can('canExecuteTool', $this->permissionContext('executeTool', ['conversation_id' => $conversationId, 'tool_name' => $toolName]))) {
            return $this->deny();
        }

        $snapshotRepo = $this->module()?->getToolSnapshotRepository();
        $snapshot = $snapshotRepo?->findOneByConversationAndTool($conversationId, $toolName);
        $definition = $snapshotRepo && $snapshot ? $snapshotRepo->toDefinition($snapshot) : null;
        if (!$definition instanceof ToolDefinition) {
            $definition = $this->module()?->getToolRegistry()->findByName($toolName, new \eseperio\aiagent\dto\ToolContext(
                conversation: $this->module()?->getConversationManager()->getConversation($conversationId),
                contexts: $this->module()?->getContextManager()->listContexts($conversationId),
                user: $this->user(),
                request: $request
            ));
        } else {
            $registryDefinition = $this->module()?->getToolRegistry()->findByName($toolName, new \eseperio\aiagent\dto\ToolContext(
                conversation: $this->module()?->getConversationManager()->getConversation($conversationId),
                contexts: $this->module()?->getContextManager()->listContexts($conversationId),
                user: $this->user(),
                request: $request
            ));
            if ($registryDefinition instanceof ToolDefinition) {
                $definition->handler ??= $registryDefinition->handler;
                $definition->requiresApproval = $registryDefinition->requiresApproval;
                $definition->available = $registryDefinition->available;
                $definition->contextTypes = $registryDefinition->contextTypes;
            }
        }

        if (!$definition instanceof ToolDefinition) {
            try {
                $definition = $this->module()?->getToolRegistry()->findResolvedByName($toolName, new \eseperio\aiagent\dto\ToolContext(
                    conversation: $this->module()?->getConversationManager()->getConversation($conversationId),
                    contexts: $this->module()?->getContextManager()->listContexts($conversationId),
                    user: $this->user(),
                    request: $request
                ));
            } catch (\RuntimeException $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
            }
            if (!$definition instanceof ToolDefinition) {
                return $this->json(['success' => false, 'error' => 'Tool not found'], 404);
            }
        }

        $toolCallId = (string)$request->post('tool_call_id', $snapshot?->id ?? '');
        $arguments = is_array($request->post('arguments')) ? $request->post('arguments') : [];
        $toolContext = new \eseperio\aiagent\dto\ToolContext(
            conversation: $this->module()?->getConversationManager()->getConversation($conversationId),
            contexts: $this->module()?->getContextManager()->listContexts($conversationId),
            user: $this->user(),
            request: $request
        );

        if (is_bool($definition->available) && !$definition->available) {
            return $this->persistUnavailableToolResult($conversationId, $definition, $snapshot?->response_id, $toolCallId, 'Tool is no longer available', $snapshotRepo, $snapshot);
        }
        if (is_callable($definition->available) && !(bool)call_user_func($definition->available, $toolContext, $definition)) {
            return $this->persistUnavailableToolResult($conversationId, $definition, $snapshot?->response_id, $toolCallId, 'Tool is no longer available', $snapshotRepo, $snapshot);
        }
        if (!empty($definition->contextTypes) && !$this->toolContextMatches($definition->contextTypes, $toolContext->contexts)) {
            return $this->persistUnavailableToolResult($conversationId, $definition, $snapshot?->response_id, $toolCallId, 'Tool is no longer available in this context', $snapshotRepo, $snapshot);
        }

        $context = new ToolExecutionContext(
            conversation: $toolContext->conversation,
            message: null,
            toolCallId: $toolCallId !== '' ? $toolCallId : null,
            responseId: $snapshot?->response_id,
            contexts: $toolContext->contexts,
            user: $toolContext->user,
            request: $request,
            toolSnapshot: $snapshot?->toArray() ?? [],
            metadata: []
        );

        $result = $this->executeHandler($definition, $context, $arguments);
        $manager = $this->module()?->getConversationManager();
        $contextManager = $this->module()?->getContextManager();
        $createdContexts = [];
        if ($contextManager && is_array($result->createdContexts)) {
            foreach ($result->createdContexts as $createdContext) {
                if (!is_array($createdContext)) {
                    continue;
                }
                $type = (int)($createdContext['type'] ?? 0);
                $metadata = is_array($createdContext['metadata'] ?? null) ? $createdContext['metadata'] : [];
                if ($type <= 0) {
                    continue;
                }
                $persistedContext = $contextManager->addContext(
                    $conversationId,
                    $type,
                    $metadata,
                    isset($createdContext['label']) ? (string)$createdContext['label'] : null,
                    (int)($createdContext['sort_order'] ?? 0)
                );
                $createdContexts[] = $persistedContext->toArray();
            }
        }
        if ($manager) {
            $manager->addMessage(
                $conversationId,
                'tool',
                'tool_result',
                $result->message ?? json_encode($result->data ?? []),
                $snapshot?->response_id,
                $toolCallId !== '' ? $toolCallId : null,
                $definition->name,
                ['success' => $result->success, 'error' => $result->error, 'data' => $result->data]
            );
        }

        if ($snapshotRepo && $snapshot) {
            $snapshot->definition_json = json_encode(array_merge(
                json_decode((string)$snapshot->definition_json, true) ?: [],
                ['last_result' => ['success' => $result->success, 'error' => $result->error]]
            ));
            $snapshot->save(false, ['definition_json']);
        }

        return $this->json([
            'success' => $result->success,
            'conversation_id' => $conversationId,
            'tool_name' => $definition->name,
            'data' => $result->data,
            'error' => $result->error,
            'created_contexts' => $createdContexts ?: $result->createdContexts,
            'updated_contexts' => $result->updatedContexts,
            'message' => $result->message,
        ]);
    }

    private function persistUnavailableToolResult(
        int $conversationId,
        ToolDefinition $definition,
        ?string $responseId,
        string $toolCallId,
        string $message,
        mixed $snapshotRepo,
        mixed $snapshot
    ): array {
        $manager = $this->module()?->getConversationManager();
        if ($manager) {
            $manager->addMessage(
                $conversationId,
                'tool',
                'tool_result',
                $message,
                $responseId,
                $toolCallId !== '' ? $toolCallId : null,
                $definition->name,
                ['success' => false, 'error' => $message, 'data' => null]
            );
        }
        if ($snapshotRepo && $snapshot) {
            $snapshot->definition_json = json_encode(array_merge(
                json_decode((string)$snapshot->definition_json, true) ?: [],
                ['last_result' => ['success' => false, 'error' => $message]]
            ));
            $snapshot->save(false, ['definition_json']);
        }

        return $this->json([
            'success' => false,
            'conversation_id' => $conversationId,
            'tool_name' => $definition->name,
            'error' => $message,
            'message' => $message,
        ], 400);
    }

    private function toolContextMatches(array $contextTypes, array $contexts): bool
    {
        foreach ($contexts as $activeContext) {
            $type = is_object($activeContext) ? ($activeContext->type ?? null) : ($activeContext['type'] ?? null);
            if ($type !== null && in_array($type, $contextTypes, true)) {
                return true;
            }
        }

        return false;
    }

    private function executeHandler(ToolDefinition $definition, ToolExecutionContext $context, array $arguments): ToolResult
    {
        $handler = $definition->handler;
        if (is_callable($handler)) {
            $result = call_user_func($handler, $context, $arguments);
            return $result instanceof ToolResult ? $result : new ToolResult(true, $result);
        }

        if (is_object($handler) && $handler instanceof ToolHandlerInterface) {
            return $handler->execute($context, $arguments);
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = \Yii::createObject($handler);
            if ($handler instanceof ToolHandlerInterface) {
                return $handler->execute($context, $arguments);
            }
        }

        return new ToolResult(false, null, 'Tool handler not configured', [], [], 'Tool handler not configured');
    }
}
