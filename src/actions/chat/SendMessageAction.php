<?php

namespace eseperio\aiagent\actions\chat;

class SendMessageAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        $message = (string)$request->post('message', '');
        if ($conversationId <= 0 || trim($message) === '') {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        if (!$this->can('canSendMessage', $this->permissionContext('sendMessage', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $manager = $this->module()?->getConversationManager();
        if (!$manager) {
            return $this->json(['success' => false, 'error' => 'Module unavailable'], 500);
        }

        $manager->addMessage($conversationId, 'user', 'message', $message);

        $requestId = bin2hex(random_bytes(8));
        $preToolSnapshots = [];
        $toolContext = new \eseperio\aiagent\dto\ToolContext(
            conversation: $manager->getConversation($conversationId),
            contexts: $this->module()?->getContextManager()->listContexts($conversationId) ?? [],
            user: $this->user(),
            request: $request,
            model: $this->module()?->resolveModel(null, $manager->getConversation($conversationId)?->model),
            metadata: ['conversation_id' => $conversationId, 'request_id' => $requestId]
        );
        $registry = $this->module()?->getToolRegistry();
        $resolvedTools = $registry?->getResolvedTools($toolContext) ?? [];
        $normalizedTools = $registry?->normalize($resolvedTools) ?? [];
        $snapshotRepo = $this->module()?->getToolSnapshotRepository();
        if ($snapshotRepo) {
            foreach ($resolvedTools as $toolDefinition) {
                if ($toolDefinition instanceof \eseperio\aiagent\dto\ToolDefinition) {
                    $preToolSnapshots[] = $snapshotRepo->save(
                        $conversationId,
                        null,
                        $toolDefinition,
                        [
                            'conversation_id' => $conversationId,
                            'context_count' => count($toolContext->contexts),
                        ],
                        $requestId
                    );
                }
            }
        }

        $payload = [
            'model' => $this->module()?->resolveModel(null, null),
            'input' => [
                ['role' => 'user', 'content' => $message],
            ],
            'tools' => $normalizedTools,
            'metadata' => ['conversation_id' => $conversationId],
        ];
        $previousResponseId = $manager->findLastResponseIdForContinuation($conversationId);
        if ($previousResponseId !== null) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        $responseService = $this->module()?->getAiResponseService();
        $response = $responseService ? $responseService->send($payload) : [];

        $parsed = $this->module()?->getResponseParser()->parse($response) ?? ['text' => null, 'tool_calls' => [], 'usage' => []];
        $assistantText = trim((string)($parsed['text'] ?? '')) ?: '...';
        $manager->addMessage($conversationId, 'assistant', 'message', $assistantText, $parsed['id'] ?? null, null, null, null, $parsed['usage'] ?? []);

        $toolCalls = is_array($parsed['tool_calls'] ?? null) ? $parsed['tool_calls'] : [];
        $pendingTools = [];
        $autoExecutedTools = [];
        $autoExecutionLimit = $this->module()?->getAutoExecutionMaxIterations() ?? 8;
        $autoExecutionCount = 0;
        if ($snapshotRepo) {
            $snapshotRepo->attachResponseIdByRequestId($conversationId, $requestId, $parsed['id'] ?? null);
        }

        if (!empty($toolCalls) && $snapshotRepo) {
            $repo = $snapshotRepo;
            foreach ($toolCalls as $toolCall) {
                $name = (string)($toolCall['name'] ?? '');
                if ($name !== '') {
                    $registeredTool = $registry?->findResolvedByName($name, $toolContext);
                    $definition = $registeredTool ?? new \eseperio\aiagent\dto\ToolDefinition(
                        $name,
                        $name,
                        ['type' => 'object', 'properties' => []]
                    );
                    $snapshot = $repo->findOneByResponseAndTool($conversationId, $parsed['id'] ?? null, $name)
                        ?? $repo->save($conversationId, $parsed['id'] ?? null, $definition, [], $requestId);
                    $manager->addMessage(
                        $conversationId,
                        'assistant',
                        'tool_call',
                        json_encode([
                            'name' => $name,
                            'arguments' => $toolCall['arguments'] ?? [],
                        ]),
                        $parsed['id'] ?? null,
                        (string)($toolCall['id'] ?? $snapshot->id),
                        $name,
                        ['snapshot_id' => $snapshot->id, 'tool_call' => $toolCall]
                    );
                    if ($definition->requiresApproval) {
                        $pendingTools[] = [
                            'name' => $definition->name,
                            'tool_call_id' => (string)($toolCall['id'] ?? $snapshot->id),
                            'requires_approval' => true,
                            'snapshot_id' => $snapshot->id,
                        ];
                    } else {
                        if ($autoExecutionCount >= $autoExecutionLimit) {
                            $pendingTools[] = [
                                'name' => $definition->name,
                                'tool_call_id' => (string)($toolCall['id'] ?? $snapshot->id),
                                'requires_approval' => false,
                                'snapshot_id' => $snapshot->id,
                                'reason' => 'auto_execution_limit_reached',
                            ];
                            continue;
                        }
                        $autoExecutedTools[] = $this->module()?->getConversationManager() ? $this->executeToolImmediately(
                            $definition,
                            $snapshot,
                            $conversationId,
                            (string)($toolCall['id'] ?? $snapshot->id),
                            is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [],
                            $request
                        ) : null;
                        $autoExecutionCount++;
                    }
                }
            }
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'actions' => [],
            'conversation_id' => $conversationId,
            'response_id' => $parsed['id'] ?? null,
            'pending_tools' => $pendingTools,
            'auto_executed_tools' => array_values(array_filter($autoExecutedTools)),
            'messages' => $manager->getMessagesForDisplay($conversationId),
        ]);
    }

    private function executeToolImmediately(
        \eseperio\aiagent\dto\ToolDefinition $definition,
        \eseperio\aiagent\models\ToolSnapshot $snapshot,
        int $conversationId,
        string $toolCallId,
        array $arguments,
        \yii\web\Request $request
    ): array {
        $toolContext = new \eseperio\aiagent\dto\ToolExecutionContext(
            conversation: $this->module()?->getConversationManager()->getConversation($conversationId),
            message: null,
            toolCallId: $toolCallId,
            responseId: $snapshot->response_id,
            contexts: $this->module()?->getContextManager()->listContexts($conversationId),
            user: $this->user(),
            request: $request,
            toolSnapshot: $snapshot->toArray(),
            metadata: ['auto_executed' => true]
        );

        $result = $this->executeHandler($definition, $toolContext, $arguments);
        $this->module()?->getConversationManager()->addMessage(
            $conversationId,
            'tool',
            'tool_result',
            $result->message ?? json_encode($result->data ?? []),
            $snapshot->response_id,
            $toolCallId,
            $definition->name,
            ['success' => $result->success, 'error' => $result->error, 'data' => $result->data]
        );

        return [
            'name' => $definition->name,
            'success' => $result->success,
            'data' => $result->data,
            'error' => $result->error,
            'message' => $result->message,
        ];
    }

    protected function executeHandler(\eseperio\aiagent\dto\ToolDefinition $definition, \eseperio\aiagent\dto\ToolExecutionContext $context, array $arguments): \eseperio\aiagent\dto\ToolResult
    {
        $handler = $definition->handler;
        if (is_callable($handler)) {
            $result = call_user_func($handler, $context, $arguments);
            return $result instanceof \eseperio\aiagent\dto\ToolResult ? $result : new \eseperio\aiagent\dto\ToolResult(true, $result);
        }

        if (is_object($handler) && $handler instanceof \eseperio\aiagent\contracts\ToolHandlerInterface) {
            return $handler->execute($context, $arguments);
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = \Yii::createObject($handler);
            if ($handler instanceof \eseperio\aiagent\contracts\ToolHandlerInterface) {
                return $handler->execute($context, $arguments);
            }
        }

        return new \eseperio\aiagent\dto\ToolResult(false, null, 'Tool handler not configured', [], [], 'Tool handler not configured');
    }
}
