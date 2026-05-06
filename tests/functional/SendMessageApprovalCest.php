<?php

namespace eseperio\aiagent\tests\functional;

class SendMessageApprovalCest
{
    public function testSendMessageReturnsPendingToolWhenApprovalIsRequired(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $I->seeResponseCodeIs(200);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'tool-call',
        ]);

        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertNotEmpty($response['pending_tools'] ?? []);
        $I->assertSame('demo_tool', $response['pending_tools'][0]['name'] ?? null);
    }

    public function testExecuteToolPersistsToolResultAndHistory(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 11,
            'label' => 'CMS',
            'metadata' => ['class' => 'Cms', 'id' => 7],
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'tool-call',
        ]);

        $sendResponse = json_decode($I->grabResponse(), true);
        $pending = $sendResponse['pending_tools'][0] ?? null;
        $I->assertIsArray($pending);

        $I->sendPost('/ai-agent/chat/execute-tool', [
            'conversation_id' => $conversation['id'],
            'tool_name' => $pending['name'],
            'tool_call_id' => $pending['tool_call_id'],
            'snapshot_id' => $pending['snapshot_id'] ?? null,
            'arguments' => ['value' => 1],
        ]);

        $executeResponse = json_decode($I->grabResponse(), true);
        $I->assertTrue($executeResponse['success'] ?? false);
        $I->assertSame('demo_tool', $executeResponse['tool_name'] ?? null);
        $I->assertSame(1, $executeResponse['data']['context_count'] ?? null);
        $I->assertNotEmpty($executeResponse['created_contexts'] ?? []);
        $I->assertSame('Created from tool', $executeResponse['created_contexts'][0]['label'] ?? null);
        $I->assertTrue($executeResponse['followup']['success'] ?? false);
        $I->assertSame('fake-after-tool-result', $executeResponse['followup']['message'] ?? null);

        $I->sendGet('/ai-agent/chat/get-history?conversation_id=' . $conversation['id']);
        $history = json_decode($I->grabResponse(), true);
        $I->assertTrue($history['success'] ?? false);
        $I->assertNotEmpty(array_filter($history['messages'] ?? [], static function (array $message): bool {
            return ($message['message_type'] ?? null) === 'tool_result';
        }));
        $I->assertNotEmpty(array_filter($history['messages'] ?? [], static function (array $message): bool {
            return ($message['role'] ?? null) === 'assistant'
                && ($message['message_type'] ?? null) === 'context'
                && str_contains((string)($message['content'] ?? ''), 'Created from tool');
        }));
        $I->assertNotEmpty(array_filter($history['messages'] ?? [], static function (array $message): bool {
            return ($message['role'] ?? null) === 'assistant'
                && ($message['message_type'] ?? null) === 'message'
                && ($message['content'] ?? null) === 'fake-after-tool-result';
        }));

        $I->sendGet('/ai-agent/chat/list-contexts?conversation_id=' . $conversation['id']);
        $contexts = json_decode($I->grabResponse(), true);
        $I->assertTrue($contexts['success'] ?? false);
        $I->assertGreaterThanOrEqual(2, count($contexts['contexts'] ?? []));
    }

    public function testGetHistoryReturnsConversationMessages(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'hello',
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendGet('/ai-agent/chat/get-history?conversation_id=' . $conversation['id']);
        $history = json_decode($I->grabResponse(), true);
        $I->assertTrue($history['success'] ?? false);
        $I->assertNotEmpty($history['messages'] ?? []);
    }

    public function testListRenameAndDeleteConversations(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendGet('/ai-agent/chat/list-conversations');
        $list = json_decode($I->grabResponse(), true);
        $I->assertTrue($list['success'] ?? false);
        $I->assertNotEmpty($list['conversations'] ?? []);

        $I->sendPost('/ai-agent/chat/rename-conversation', [
            'conversation_id' => $conversation['id'],
            'title' => 'Renamed conversation',
        ]);
        $rename = json_decode($I->grabResponse(), true);
        $I->assertTrue($rename['success'] ?? false);

        $I->sendPost('/ai-agent/chat/delete-conversation', [
            'conversation_id' => $conversation['id'],
        ]);
        $delete = json_decode($I->grabResponse(), true);
        $I->assertTrue($delete['success'] ?? false);
    }

    public function testContinueConversationAndContexts(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 10,
            'label' => 'Product',
            'metadata' => ['class' => 'Product', 'id' => 123],
        ]);
        $addContext = json_decode($I->grabResponse(), true);
        $I->assertTrue($addContext['success'] ?? false);

        $I->sendGet('/ai-agent/chat/list-contexts?conversation_id=' . $conversation['id']);
        $contexts = json_decode($I->grabResponse(), true);
        $I->assertTrue($contexts['success'] ?? false);
        $I->assertNotEmpty($contexts['contexts'] ?? []);

        $I->sendGet('/ai-agent/chat/get-history?conversation_id=' . $conversation['id']);
        $history = json_decode($I->grabResponse(), true);
        $I->assertTrue($history['success'] ?? false);
        $I->assertNotEmpty(array_filter($history['messages'] ?? [], static function (array $message): bool {
            return ($message['message_type'] ?? null) === 'context'
                && ($message['virtual'] ?? false) === true
                && str_contains((string)($message['content'] ?? ''), 'Product');
        }));

        $I->sendPost('/ai-agent/chat/continue-conversation', [
            'conversation_id' => $conversation['id'],
        ]);
        $continue = json_decode($I->grabResponse(), true);
        $I->assertTrue($continue['success'] ?? false);
        $I->assertSame('active', $continue['conversation']['status'] ?? null);
    }

    public function testMultipleContextsStayActiveInConversation(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 10,
            'label' => 'Product',
            'metadata' => ['class' => 'Product', 'id' => 1],
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 20,
            'label' => 'CMS',
            'metadata' => ['class' => 'Cms', 'id' => 2],
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendGet('/ai-agent/chat/list-contexts?conversation_id=' . $conversation['id']);
        $contexts = json_decode($I->grabResponse(), true);
        $I->assertTrue($contexts['success'] ?? false);
        $I->assertCount(2, $contexts['contexts'] ?? []);
    }

    public function testSendMessageAutoExecutesReadOnlyTool(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'auto-tool',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertEmpty($response['pending_tools'] ?? []);
        $I->assertNotEmpty($response['auto_executed_tools'] ?? []);
        $I->assertSame('auto_demo_tool', $response['auto_executed_tools'][0]['name'] ?? null);
        $I->assertSame(true, $response['auto_executed_tools'][0]['data']['auto'] ?? null);
        $I->assertTrue($response['followup']['success'] ?? false);
        $I->assertSame('fake-after-tool-result', $response['followup']['message'] ?? null);
        $I->assertNotEmpty(array_filter($response['messages'] ?? [], static function (array $message): bool {
            return ($message['role'] ?? null) === 'assistant'
                && ($message['message_type'] ?? null) === 'message'
                && ($message['content'] ?? null) === 'fake-after-tool-result';
        }));
    }

    public function testSendMessageStopsAutoExecutionAtConfiguredLimit(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'auto-tool-many',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertCount(3, $response['auto_executed_tools'] ?? []);
        $I->assertNotEmpty($response['pending_tools'] ?? []);
        $I->assertSame('auto_execution_limit_reached', $response['pending_tools'][0]['reason'] ?? null);
    }

    public function testSendMessageWithFunctionCallAutoExecutes(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'function-call-auto',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertNotEmpty($response['auto_executed_tools'] ?? []);
        $I->assertSame('auto_demo_tool', $response['auto_executed_tools'][0]['name'] ?? null);
        $I->assertTrue($response['followup']['success'] ?? false);
    }

    public function testSendMessagePersistsUsageTokens(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'hello',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertGreaterThan(0, $response['messages'][1]['input_tokens'] ?? 0);
        $I->assertGreaterThan(0, $response['messages'][1]['output_tokens'] ?? 0);
        $I->assertGreaterThan(0, $response['messages'][1]['total_tokens'] ?? 0);
    }

    public function testSendMessagePersistsStructuredQuestionnaireSeparately(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'questionnaire',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $messages = $response['messages'] ?? [];
        $questionnaires = array_values(array_filter($messages, static function (array $message): bool {
            return ($message['message_type'] ?? null) === 'questionnaire';
        }));

        $I->assertNotEmpty($questionnaires);
        $questionnaire = json_decode($questionnaires[0]['content'] ?? '{}', true);
        $I->assertTrue($questionnaire['enabled'] ?? false);
        $I->assertSame('single_choice', $questionnaire['questions'][0]['type'] ?? null);
        $I->assertSame('Producto', $questionnaire['questions'][0]['options'][0]['label'] ?? null);
        $I->assertCount(2, $questionnaire['questions'] ?? []);
    }

    public function testSendMessageReturnsJsonWhenProviderReturnsError(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'provider-error',
        ]);

        $I->seeResponseCodeIs(502);
        $response = json_decode($I->grabResponse(), true);
        $I->assertFalse($response['success'] ?? true);
        $I->assertSame('Simulated provider error', $response['error'] ?? null);
        $I->assertNotEmpty($response['messages'] ?? []);
    }

    public function testExecuteToolWithClassHandler(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 10,
            'label' => 'Product',
            'metadata' => ['class' => 'Product', 'id' => 1],
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'class-demo-tool',
        ]);
        $sendResponse = json_decode($I->grabResponse(), true);
        $pending = $sendResponse['pending_tools'][0] ?? null;
        $I->assertSame('class_demo_tool', $pending['name'] ?? null);

        $I->sendPost('/ai-agent/chat/execute-tool', [
            'conversation_id' => $conversation['id'],
            'tool_name' => 'class_demo_tool',
            'tool_call_id' => $pending['tool_call_id'] ?? null,
            'arguments' => ['value' => 7],
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);
        $I->assertSame('7', $response['data']['handled'] ?? null);
    }

    public function testExecuteToolDeniedWhenPolicyBlocksEffect(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/execute-tool', [
            'conversation_id' => $conversation['id'],
            'tool_name' => 'blocked_delete_tool',
            'arguments' => ['value' => 1],
        ]);

        $I->seeResponseCodeIs(403);
        $response = json_decode($I->grabResponse(), true);
        $I->assertFalse($response['success'] ?? true);
        $I->assertSame('tool_effect_blocked', $response['policy']['reason'] ?? null);
        $I->assertSame('delete', $response['policy']['effect'] ?? null);
    }

    public function testInlineToolCallDoesNotDuplicateRows(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/send-message', [
            'conversation_id' => $conversation['id'],
            'message' => 'tool-call',
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['success'] ?? false);

        $I->sendGet('/ai-agent/chat/get-history?conversation_id=' . $conversation['id']);
        $history = json_decode($I->grabResponse(), true);
        $toolCalls = array_values(array_filter($history['messages'] ?? [], static function (array $message): bool {
            return ($message['message_type'] ?? null) === 'tool_call';
        }));

        $I->assertCount(1, $toolCalls);
    }

    public function testRenderContextsReturnsCardsAndRespectsPermission(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation', []);
        $conversation = json_decode($I->grabResponse(), true)['conversation'] ?? null;
        $I->assertIsArray($conversation);

        $I->sendPost('/ai-agent/chat/add-context', [
            'conversation_id' => $conversation['id'],
            'type' => 99,
            'label' => 'Rendered context',
            'metadata' => ['class' => 'Cms', 'id' => 88],
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendGet('/ai-agent/chat/render-contexts?conversation_id=' . $conversation['id']);
        $rendered = json_decode($I->grabResponse(), true);
        $I->assertTrue($rendered['success'] ?? false);
        $I->assertNotEmpty($rendered['contexts'] ?? []);
        $I->assertNotEmpty(trim((string)($rendered['html'] ?? '')));

        $I->sendPost('/ai-agent/chat/render-contexts', [
            'conversation_id' => $conversation['id'],
            'deny_render_context' => 1,
        ]);
        $I->seeResponseCodeIs(403);
    }

    public function testJsonActionsReturnStableErrorStructureAndHttpCodes(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/execute-tool', []);
        $I->seeResponseCodeIs(400);
        $invalid = json_decode($I->grabResponse(), true);
        $I->assertFalse($invalid['success'] ?? true);
        $I->assertSame('Invalid parameters', $invalid['error'] ?? null);

        $I->sendPost('/ai-agent/chat/continue-conversation', [
            'conversation_id' => 999999,
        ]);
        $I->seeResponseCodeIs(404);
        $missing = json_decode($I->grabResponse(), true);
        $I->assertFalse($missing['success'] ?? true);
        $I->assertSame('Conversation not found', $missing['error'] ?? null);

        $I->sendPost('/ai-agent/chat/render-contexts', [
            'conversation_id' => 1,
            'deny_render_context' => 1,
        ]);
        $I->seeResponseCodeIs(403);
        $denied = json_decode($I->grabResponse(), true);
        $I->assertFalse($denied['success'] ?? true);
        $I->assertSame('Forbidden', $denied['error'] ?? null);
    }
}
