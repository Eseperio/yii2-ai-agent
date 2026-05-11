<?php

namespace eseperio\aiagent\actions\chat;

class SendMessageAction extends BaseChatAction
{
    public function run()
    {
        $request = $this->request();
        $conversationId = (int)$request->post('conversation_id', 0);
        $message = (string)$request->post('message', '');
        $assetIds = $this->normalizeAssetIds($request->post('asset_ids', []));
        if ($conversationId <= 0 || (trim($message) === '' && $assetIds === [])) {
            return $this->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        if (!$this->can('canSendMessage', $this->permissionContext('sendMessage', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $manager = $this->module()?->getConversationManager();
        if (!$manager) {
            return $this->json(['success' => false, 'error' => 'Module unavailable'], 500);
        }

        $conversation = $manager->getConversation($conversationId);
        if (!$conversation || $conversation->status === 'deleted') {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        if (!$this->can('canContinueChat', $this->permissionContext('continueConversation', ['conversation_id' => $conversationId]))) {
            return $this->deny();
        }

        $userMessage = $manager->addMessage($conversationId, 'user', 'message', $message);
        if ($assetIds !== []) {
            $manager->linkAssetsToMessage((int)$userMessage->id, $assetIds, \eseperio\aiagent\models\MessageAsset::USAGE_INPUT);
        }

        $requestId = bin2hex(random_bytes(8));
        $preToolSnapshots = [];
        $contexts = $this->module()?->getContextManager()->listContexts($conversationId) ?? [];
        $model = $this->module()?->resolveModel(null, $conversation->model) ?? null;
        $toolContext = new \eseperio\aiagent\dto\ToolContext(
            conversation: $conversation,
            contexts: $contexts,
            user: $this->user(),
            request: $request,
            model: $model,
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
            'model' => $model,
            'input' => [
                ['role' => 'user', 'content' => $this->buildUserContent($message, $assetIds)],
            ],
            'tools' => $normalizedTools,
            'contexts' => $contexts,
            'metadata' => ['conversation_id' => $conversationId],
        ];
        $previousResponseId = $manager->findLastResponseIdForContinuation($conversationId);
        if ($previousResponseId !== null) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        $responseService = $this->module()?->getAiResponseService();
        try {
            $response = $responseService ? $responseService->send($payload) : [];
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            $manager->addMessage(
                $conversationId,
                'assistant',
                'error',
                'The AI provider could not process the request.'
            );

            return $this->json([
                'success' => false,
                'error' => 'The AI provider could not process the request.',
                'conversation_id' => $conversationId,
                'messages' => $manager->getMessagesForDisplay($conversationId),
            ], 502);
        }

        if (isset($response['error'])) {
            $messageText = is_array($response['error'])
                ? (string)($response['error']['message'] ?? 'The AI provider returned an error.')
                : (string)$response['error'];
            $manager->addMessage($conversationId, 'assistant', 'error', $messageText);

            return $this->json([
                'success' => false,
                'error' => $messageText,
                'conversation_id' => $conversationId,
                'messages' => $manager->getMessagesForDisplay($conversationId),
            ], 502);
        }

        $parsed = $this->module()?->getResponseParser()->parse($response) ?? ['text' => null, 'tool_calls' => [], 'usage' => []];
        $toolCalls = is_array($parsed['tool_calls'] ?? null) ? $parsed['tool_calls'] : [];
        $assistantPayload = $this->module()?->getResponseParser()->parseText((string)($parsed['text'] ?? '')) ?? [];
        $assistantText = $this->resolveAssistantText($assistantPayload, (string)($parsed['text'] ?? ''));
        if ($assistantText !== '...' || !$toolCalls) {
            $manager->addMessage($conversationId, 'assistant', 'message', $assistantText, $parsed['id'] ?? null, null, null, null, $parsed['usage'] ?? []);
            $this->applyConversationTitleSuggestion($manager, $conversation, $assistantPayload);
        }

        $questionnaire = $this->normalizeQuestionnaire($assistantPayload['questionnaire'] ?? null);
        if ($questionnaire !== null) {
            $manager->addMessage(
                $conversationId,
                'assistant',
                'questionnaire',
                json_encode($questionnaire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $parsed['id'] ?? null
            );
        }

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
                    $toolCallId = trim((string)($toolCall['id'] ?? ''));
                    if ($toolCallId === '') {
                        $toolCallId = (string)$snapshot->id;
                    }
                    $arguments = is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [];
                    $executionContext = new \eseperio\aiagent\dto\ToolExecutionContext(
                        conversation: $conversation,
                        message: null,
                        toolCallId: $toolCallId,
                        responseId: $parsed['id'] ?? null,
                        contexts: $toolContext->contexts,
                        user: $this->user(),
                        request: $request,
                        toolSnapshot: $snapshot->toArray(),
                        metadata: ['auto_policy_check' => true]
                    );
                    $policy = $this->module()?->getToolPolicy()->decide($definition, $executionContext, $arguments);
                    $manager->addMessage(
                        $conversationId,
                        'assistant',
                        'tool_call',
                        json_encode([
                            'name' => $name,
                            'arguments' => $toolCall['arguments'] ?? [],
                        ]),
                        $parsed['id'] ?? null,
                        $toolCallId,
                        $name,
                        [
                            'snapshot_id' => $snapshot->id,
                            'tool_call' => $toolCall,
                            'tool_metadata' => $definition->metadata,
                            'requires_approval' => $policy?->requiresApproval ?? $definition->requiresApproval,
                            'policy' => [
                                'allowed' => $policy?->allowed ?? true,
                                'effect' => $policy?->effect ?? null,
                                'risk_level' => $policy?->riskLevel ?? null,
                                'reason' => $policy?->reason ?? null,
                            ],
                        ]
                    );
                    if ($policy && !$policy->allowed) {
                        $pendingTools[] = [
                            'name' => $definition->name,
                            'tool_call_id' => $toolCallId,
                            'requires_approval' => true,
                            'snapshot_id' => $snapshot->id,
                            'reason' => $policy->reason,
                        ];
                    } elseif ($policy?->requiresApproval) {
                        $pendingTools[] = [
                            'name' => $definition->name,
                            'tool_call_id' => $toolCallId,
                            'requires_approval' => true,
                            'snapshot_id' => $snapshot->id,
                        ];
                    } else {
                        if ($policy && !$policy->allowAutonomous) {
                            $pendingTools[] = [
                                'name' => $definition->name,
                                'tool_call_id' => $toolCallId,
                                'requires_approval' => true,
                                'snapshot_id' => $snapshot->id,
                                'reason' => 'autonomous_execution_not_allowed',
                            ];
                            continue;
                        }
                        if ($autoExecutionCount >= $autoExecutionLimit) {
                            $pendingTools[] = [
                                'name' => $definition->name,
                                'tool_call_id' => $toolCallId,
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
                            $toolCallId,
                            $arguments,
                            $request
                        ) : null;
                        $autoExecutionCount++;
                    }
                }
            }
        }

        $followUp = null;
        if ($autoExecutedTools && !$pendingTools && !empty($parsed['id'])) {
            $followUp = $this->continueAfterAutoToolResults(
                $conversationId,
                $model,
                (string)$parsed['id'],
                array_values(array_filter($autoExecutedTools)),
                $request
            );
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'actions' => [],
            'conversation_id' => $conversationId,
            'response_id' => $parsed['id'] ?? null,
            'pending_tools' => $pendingTools,
            'auto_executed_tools' => array_values(array_filter($autoExecutedTools)),
            'followup' => $followUp,
            'messages' => $manager->getMessagesForDisplay($conversationId),
        ]);
    }

    private function normalizeAssetIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    private function buildUserContent(string $message, array $assetIds): string|array
    {
        if ($assetIds === []) {
            return $message;
        }

        $chunks = [];
        $message = trim($message);
        if ($message !== '') {
            $chunks[] = ['type' => 'input_text', 'text' => $message];
        }

        $class = $this->module()?->assetClass;
        $assets = $class ? $class::find()->where(['id' => $assetIds])->all() : [];
        foreach ($assets as $asset) {
            if (($asset->type ?? null) === \eseperio\aiagent\models\Asset::TYPE_IMAGE) {
                $chunks[] = [
                    'type' => 'input_image',
                    'image_url' => $this->module()->getAssetService()->dataUrl($asset),
                ];
                continue;
            }
            $chunks[] = [
                'type' => 'input_text',
                'text' => 'Archivo adjunto: ' . $asset->filename . ' (' . $asset->mime_type . ').',
            ];
        }

        return $chunks ?: $message;
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
        if ($suggestion === '') {
            return;
        }

        $currentTitle = trim((string)$conversation->title);
        if ($currentTitle === '') {
            $manager->renameConversation((int)$conversation->id, mb_substr($suggestion, 0, 120));
        }
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
        $createdContexts = [];
        $contextManager = $this->module()?->getContextManager();
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
                $this->addContextPreviewMessage($conversationId, $persistedContext, $snapshot->response_id);
            }
        }
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
            'tool_call_id' => $toolCallId,
            'output' => [
                'success' => $result->success,
                'tool_name' => $definition->name,
                'data' => $result->data,
                'error' => $result->error,
                'message' => $result->message,
                'created_contexts' => $createdContexts ?: $result->createdContexts,
                'updated_contexts' => $result->updatedContexts,
            ],
            'success' => $result->success,
            'data' => $result->data,
            'error' => $result->error,
            'message' => $result->message,
            'created_contexts' => $createdContexts ?: $result->createdContexts,
        ];
    }

    private function continueAfterAutoToolResults(
        int $conversationId,
        ?string $model,
        string $previousResponseId,
        array $autoExecutedTools,
        \yii\web\Request $request
    ): array {
        $inputs = [];
        foreach ($autoExecutedTools as $toolResult) {
            $toolCallId = (string)($toolResult['tool_call_id'] ?? '');
            if ($toolCallId === '') {
                continue;
            }
            $inputs[] = [
                'type' => 'function_call_output',
                'call_id' => $toolCallId,
                'output' => json_encode($toolResult['output'] ?? [
                    'success' => $toolResult['success'] ?? false,
                    'tool_name' => $toolResult['name'] ?? '',
                    'data' => $toolResult['data'] ?? null,
                    'error' => $toolResult['error'] ?? null,
                    'message' => $toolResult['message'] ?? null,
                    'created_contexts' => $toolResult['created_contexts'] ?? [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        if (!$inputs) {
            return ['success' => false, 'skipped' => true, 'reason' => 'missing_tool_outputs'];
        }

        $manager = $this->module()?->getConversationManager();
        $registry = $this->module()?->getToolRegistry();
        $snapshotRepo = $this->module()?->getToolSnapshotRepository();
        $conversation = $manager?->getConversation($conversationId);
        $contexts = $this->module()?->getContextManager()->listContexts($conversationId) ?? [];
        $payload = [
            'model' => $model,
            'previous_response_id' => $previousResponseId,
            'input' => $inputs,
            'contexts' => $contexts,
            'metadata' => ['conversation_id' => $conversationId, 'auto_tool_results' => count($inputs)],
        ];

        try {
            $response = $this->module()?->getAiResponseService()->send($payload) ?? [];
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            $manager?->addMessage($conversationId, 'assistant', 'error', 'The AI provider could not process the tool result.', $previousResponseId);
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
            $toolDefinition = $registeredTool ?? new \eseperio\aiagent\dto\ToolDefinition($name, $name, ['type' => 'object', 'properties' => []]);
            $snapshot = $snapshotRepo?->save($conversationId, $parsed['id'] ?? null, $toolDefinition, [], null);
            $toolCallId = trim((string)($toolCall['id'] ?? ''));
            if ($toolCallId === '') {
                $toolCallId = (string)($snapshot?->id ?? '');
            }
            $policy = $this->module()?->getToolPolicy()->decide(
                $toolDefinition,
                new \eseperio\aiagent\dto\ToolExecutionContext(
                    conversation: $conversation,
                    message: null,
                    toolCallId: $toolCallId,
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
                $toolCallId,
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
                'tool_call_id' => $toolCallId,
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

    protected function executeHandler(\eseperio\aiagent\dto\ToolDefinition $definition, \eseperio\aiagent\dto\ToolExecutionContext $context, array $arguments): \eseperio\aiagent\dto\ToolResult
    {
        $policy = $this->module()?->getToolPolicy()->decide($definition, $context, $arguments);
        if ($policy && !$policy->allowed) {
            return new \eseperio\aiagent\dto\ToolResult(false, null, $policy->reason ?? 'Tool execution denied by policy', [], [], 'Tool execution denied by policy');
        }

        $execution = $this->module()?->getExecutionJournal()->start($definition, $context, $arguments, [
            'effect' => $policy?->effect,
            'riskLevel' => $policy?->riskLevel,
        ]);
        $handler = $definition->handler;
        if (is_callable($handler)) {
            $result = call_user_func($handler, $context, $arguments);
            $result = $result instanceof \eseperio\aiagent\dto\ToolResult ? $result : new \eseperio\aiagent\dto\ToolResult(true, $result);
            $this->module()?->getExecutionJournal()->finish($execution, $result);
            return $result;
        }

        if (is_object($handler) && $handler instanceof \eseperio\aiagent\contracts\ToolHandlerInterface) {
            $result = $handler->execute($context, $arguments);
            $this->module()?->getExecutionJournal()->finish($execution, $result);
            return $result;
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = \Yii::createObject($handler);
            if ($handler instanceof \eseperio\aiagent\contracts\ToolHandlerInterface) {
                $result = $handler->execute($context, $arguments);
                $this->module()?->getExecutionJournal()->finish($execution, $result);
                return $result;
            }
        }

        $result = new \eseperio\aiagent\dto\ToolResult(false, null, 'Tool handler not configured', [], [], 'Tool handler not configured');
        $this->module()?->getExecutionJournal()->finish($execution, $result);
        return $result;
    }
}
