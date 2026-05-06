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
        $snapshotId = (int)$request->post('snapshot_id', 0);
        $snapshot = $snapshotId > 0
            ? $snapshotRepo?->findOneByIdAndConversation($snapshotId, $conversationId)
            : $snapshotRepo?->findOneByConversationAndTool($conversationId, $toolName);
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

        $policy = $this->module()?->getToolPolicy()->decide($definition, $context, $arguments);
        if ($policy && !$policy->allowed) {
            $result = new ToolResult(false, null, $policy->reason ?? 'Tool execution denied by policy', [], [], 'Tool execution denied by policy');
            $manager = $this->module()?->getConversationManager();
            $manager?->addMessage(
                $conversationId,
                'tool',
                'tool_result',
                $result->message ?? $result->error ?? 'Tool execution denied by policy',
                $snapshot?->response_id,
                $toolCallId !== '' ? $toolCallId : null,
                $definition->name,
                ['success' => false, 'error' => $result->error, 'data' => null]
            );

            return $this->json([
                'success' => false,
                'conversation_id' => $conversationId,
                'tool_name' => $definition->name,
                'error' => $result->error,
                'message' => $result->message,
                'policy' => [
                    'effect' => $policy->effect,
                    'risk_level' => $policy->riskLevel,
                    'reason' => $policy->reason,
                ],
                'messages' => $manager?->getMessagesForDisplay($conversationId) ?? [],
            ], 403);
        }

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
                $this->addContextPreviewMessage($conversationId, $persistedContext, $snapshot?->response_id);
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

        $followUp = $this->continueAfterToolResult(
            $conversationId,
            $toolCallId,
            $definition,
            $result,
            $snapshot?->response_id,
            $request
        );

        return $this->json([
            'success' => $result->success,
            'conversation_id' => $conversationId,
            'tool_name' => $definition->name,
            'data' => $result->data,
            'error' => $result->error,
            'created_contexts' => $createdContexts ?: $result->createdContexts,
            'updated_contexts' => $result->updatedContexts,
            'message' => $result->message,
            'followup' => $followUp,
            'pending_tools' => $followUp['pending_tools'] ?? [],
            'messages' => $manager?->getMessagesForDisplay($conversationId) ?? [],
        ]);
    }

    private function continueAfterToolResult(
        int $conversationId,
        string $toolCallId,
        ToolDefinition $definition,
        ToolResult $result,
        ?string $previousResponseId,
        \yii\web\Request $request
    ): array {
        if ($previousResponseId === null || $previousResponseId === '' || $toolCallId === '') {
            return ['success' => false, 'skipped' => true, 'reason' => 'missing_previous_response_or_tool_call_id'];
        }

        $manager = $this->module()?->getConversationManager();
        $registry = $this->module()?->getToolRegistry();
        $snapshotRepo = $this->module()?->getToolSnapshotRepository();
        $conversation = $manager?->getConversation($conversationId);
        $contexts = $this->module()?->getContextManager()->listContexts($conversationId) ?? [];
        $model = $this->module()?->resolveModel(null, $conversation?->model) ?? null;
        $output = [
            'success' => $result->success,
            'tool_name' => $definition->name,
            'data' => $result->data,
            'error' => $result->error,
            'message' => $result->message,
            'created_contexts' => $result->createdContexts,
            'updated_contexts' => $result->updatedContexts,
        ];

        $payload = [
            'model' => $model,
            'previous_response_id' => $previousResponseId,
            'input' => [[
                'type' => 'function_call_output',
                'call_id' => $toolCallId,
                'output' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]],
            'contexts' => $contexts,
            'metadata' => ['conversation_id' => $conversationId, 'tool_name' => $definition->name],
        ];

        try {
            $response = $this->module()?->getAiResponseService()->send($payload) ?? [];
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            $manager?->addMessage(
                $conversationId,
                'assistant',
                'error',
                'The AI provider could not process the tool result.',
                $previousResponseId
            );

            return ['success' => false, 'error' => 'The AI provider could not process the tool result.'];
        }

        if (isset($response['error'])) {
            $messageText = is_array($response['error'])
                ? (string)($response['error']['message'] ?? 'The AI provider returned an error.')
                : (string)$response['error'];
            $manager?->addMessage($conversationId, 'assistant', 'error', $messageText, $previousResponseId);
            return ['success' => false, 'error' => $messageText];
        }

        $parsed = $this->module()?->getResponseParser()->parse($response) ?? ['text' => null, 'tool_calls' => [], 'usage' => []];
        $toolCalls = is_array($parsed['tool_calls'] ?? null) ? $parsed['tool_calls'] : [];
        $assistantPayload = $this->module()?->getResponseParser()->parseText((string)($parsed['text'] ?? '')) ?? [];
        $assistantText = $this->resolveAssistantText($assistantPayload, (string)($parsed['text'] ?? ''));
        if ($assistantText !== '...' || !$toolCalls) {
            $manager?->addMessage($conversationId, 'assistant', 'message', $assistantText, $parsed['id'] ?? null, null, null, null, $parsed['usage'] ?? []);
        }
        if ($assistantText !== '...' && $manager && $conversation) {
            $this->applyConversationTitleSuggestion($manager, $conversation, $assistantPayload);
        }

        $questionnaire = $this->normalizeQuestionnaire($assistantPayload['questionnaire'] ?? null);
        if ($questionnaire !== null) {
            $manager?->addMessage(
                $conversationId,
                'assistant',
                'questionnaire',
                json_encode($questionnaire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $parsed['id'] ?? null
            );
        }

        $toolContext = new \eseperio\aiagent\dto\ToolContext(
            conversation: $conversation,
            contexts: $contexts,
            user: $this->user(),
            request: $request,
            model: $model,
            metadata: ['conversation_id' => $conversationId]
        );
        $pendingTools = [];
        foreach ($toolCalls as $toolCall) {
            $name = (string)($toolCall['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $registeredTool = $registry?->findResolvedByName($name, $toolContext);
            $toolDefinition = $registeredTool ?? new ToolDefinition($name, $name, ['type' => 'object', 'properties' => []]);
            $snapshot = $snapshotRepo?->save($conversationId, $parsed['id'] ?? null, $toolDefinition, [], null);
            $nextToolCallId = trim((string)($toolCall['id'] ?? ''));
            if ($nextToolCallId === '') {
                $nextToolCallId = (string)($snapshot?->id ?? '');
            }
            $policy = $this->module()?->getToolPolicy()->decide(
                $toolDefinition,
                new ToolExecutionContext(
                    conversation: $conversation,
                    message: null,
                    toolCallId: $nextToolCallId,
                    responseId: $parsed['id'] ?? null,
                    contexts: $contexts,
                    user: $this->user(),
                    request: $request,
                    toolSnapshot: $snapshot?->toArray() ?? [],
                    metadata: ['auto_policy_check' => true]
                ),
                is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : []
            );
            $manager?->addMessage(
                $conversationId,
                'assistant',
                'tool_call',
                json_encode([
                    'name' => $name,
                    'arguments' => $toolCall['arguments'] ?? [],
                ]),
                $parsed['id'] ?? null,
                $nextToolCallId,
                $name,
                [
                    'snapshot_id' => $snapshot?->id,
                    'tool_call' => $toolCall,
                    'tool_metadata' => $toolDefinition->metadata,
                    'requires_approval' => $policy?->requiresApproval ?? $toolDefinition->requiresApproval,
                    'policy' => [
                        'allowed' => $policy?->allowed ?? true,
                        'effect' => $policy?->effect ?? null,
                        'risk_level' => $policy?->riskLevel ?? null,
                        'reason' => $policy?->reason ?? null,
                    ],
                ]
            );
            $pendingTools[] = [
                'name' => $toolDefinition->name,
                'tool_call_id' => $nextToolCallId,
                'requires_approval' => $policy?->requiresApproval ?? $toolDefinition->requiresApproval,
                'snapshot_id' => $snapshot?->id,
                'reason' => $policy?->allowed === false ? $policy->reason : null,
            ];
        }

        return [
            'success' => true,
            'response_id' => $parsed['id'] ?? null,
            'message' => $assistantText,
            'pending_tools' => $pendingTools,
        ];
    }

    private function resolveAssistantText(array $payload, string $rawText): string
    {
        if (array_key_exists('response', $payload)) {
            $text = trim((string)$payload['response']);
            if ($text !== '') {
                return $text;
            }
            if (!empty($payload['questionnaire']['enabled'])) {
                return trim((string)($payload['questionnaire']['title'] ?? '')) ?: 'Necesito confirmar algunos datos.';
            }
        }

        if (array_key_exists('text', $payload)) {
            $text = trim((string)$payload['text']);
            if ($text !== '') {
                return $text;
            }
        }

        return trim($rawText) !== '' ? trim($rawText) : '...';
    }

    private function applyConversationTitleSuggestion(
        \eseperio\aiagent\services\ConversationManager $manager,
        \eseperio\aiagent\models\Conversation $conversation,
        array $payload
    ): void {
        $suggestion = trim((string)($payload['conversation_title_suggestion'] ?? ''));
        if ($suggestion === '' || trim((string)$conversation->title) !== '') {
            return;
        }

        $manager->renameConversation((int)$conversation->id, mb_substr($suggestion, 0, 120));
    }

    private function normalizeQuestionnaire(mixed $questionnaire): ?array
    {
        if (!is_array($questionnaire) || empty($questionnaire['enabled'])) {
            return null;
        }

        $questions = [];
        foreach (($questionnaire['questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }
            $label = trim((string)($question['label'] ?? $question['text'] ?? $question['name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = (string)($question['type'] ?? 'text');
            if (!in_array($type, ['text', 'single_choice', 'multiple_choice'], true)) {
                $type = 'text';
            }

            $options = [];
            foreach (($question['options'] ?? []) as $option) {
                if (is_array($option)) {
                    $value = trim((string)($option['value'] ?? $option['label'] ?? ''));
                    $optionLabel = trim((string)($option['label'] ?? $option['value'] ?? ''));
                } else {
                    $value = trim((string)$option);
                    $optionLabel = $value;
                }
                if ($value === '' && $optionLabel === '') {
                    continue;
                }
                $options[] = [
                    'value' => $value !== '' ? $value : $optionLabel,
                    'label' => $optionLabel !== '' ? $optionLabel : $value,
                ];
            }

            $questions[] = [
                'id' => trim((string)($question['id'] ?? '')) ?: 'question_' . (count($questions) + 1),
                'label' => $label,
                'type' => $type,
                'required' => (bool)($question['required'] ?? false),
                'placeholder' => (string)($question['placeholder'] ?? ''),
                'options' => $type === 'text' ? [] : $options,
            ];
        }

        if (!$questions) {
            return null;
        }

        return [
            'enabled' => true,
            'title' => trim((string)($questionnaire['title'] ?? '')) ?: 'Necesito confirmar algunos datos',
            'description' => trim((string)($questionnaire['description'] ?? '')),
            'questions' => $questions,
        ];
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
        $policy = $this->module()?->getToolPolicy()->decide($definition, $context, $arguments);
        $execution = $this->module()?->getExecutionJournal()->start($definition, $context, $arguments, [
            'effect' => $policy?->effect,
            'riskLevel' => $policy?->riskLevel,
        ]);
        $handler = $definition->handler;
        if (is_callable($handler)) {
            $result = call_user_func($handler, $context, $arguments);
            $result = $result instanceof ToolResult ? $result : new ToolResult(true, $result);
            $this->module()?->getExecutionJournal()->finish($execution, $result);
            return $result;
        }

        if (is_object($handler) && $handler instanceof ToolHandlerInterface) {
            $result = $handler->execute($context, $arguments);
            $this->module()?->getExecutionJournal()->finish($execution, $result);
            return $result;
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = \Yii::createObject($handler);
            if ($handler instanceof ToolHandlerInterface) {
                $result = $handler->execute($context, $arguments);
                $this->module()?->getExecutionJournal()->finish($execution, $result);
                return $result;
            }
        }

        $result = new ToolResult(false, null, 'Tool handler not configured', [], [], 'Tool handler not configured');
        $this->module()?->getExecutionJournal()->finish($execution, $result);
        return $result;
    }
}
