<?php

namespace eseperio\aiagent\tests\functional;

class McpCest
{
    public function testMcpRequiresBearerAuth(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ]);

        $I->seeResponseCodeIs(401);
        $I->seeHttpHeader('WWW-Authenticate');
        $I->assertStringContainsString(
            'resource_metadata="http://localhost/mcp/.well-known/oauth-protected-resource"',
            (string)$I->grabHttpHeader('WWW-Authenticate')
        );
    }

    public function testMcpMetadataEndpoints(\FunctionalTester $I): void
    {
        $I->sendGet('/mcp/.well-known/oauth-protected-resource');
        $I->seeResponseCodeIs(200);
        $resource = json_decode($I->grabResponse(), true);
        $I->assertSame('http://localhost/mcp', $resource['resource'] ?? null);
        $I->assertContains('test.read', $resource['scopes_supported'] ?? []);
        $I->assertSame([], $resource['authorization_servers'] ?? null);

        $I->sendGet('/mcp/.well-known/oauth-authorization-server');
        $I->seeResponseCodeIs(404);
    }

    public function testMcpInitializeAndToolsList(\FunctionalTester $I): void
    {
        $this->authorize($I, ['test.read']);

        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ]);
        $I->seeResponseCodeIs(200);
        $initialize = json_decode($I->grabResponse(), true);
        $I->assertSame('Yii2 AI Agent MCP', $initialize['result']['serverInfo']['name'] ?? null);
        $I->assertSame('Read-only test connector.', $initialize['result']['instructions'] ?? null);

        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);
        $I->seeResponseCodeIs(200);
        $list = json_decode($I->grabResponse(), true);
        $tools = array_column($list['result']['tools'] ?? [], 'name');
        $I->assertContains('auto_demo_tool', $tools);
        $I->assertNotContains('demo_tool', $tools);
        $I->assertNotContains('blocked_delete_tool', $tools);
        $I->assertNotContains('remote_write_demo_tool', $tools);
        $autoTool = $list['result']['tools'][array_search('auto_demo_tool', $tools, true)] ?? [];
        $I->assertTrue($autoTool['annotations']['readOnlyHint'] ?? false);
    }

    public function testMcpCallToolAndWriteScopeImpliesRead(\FunctionalTester $I): void
    {
        $this->authorize($I, ['test.write']);

        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
        $I->seeResponseCodeIs(200);
        $list = json_decode($I->grabResponse(), true);
        $tools = array_column($list['result']['tools'] ?? [], 'name');
        $I->assertContains('auto_demo_tool', $tools);
        $I->assertContains('demo_tool', $tools);

        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'auto_demo_tool',
                'arguments' => ['value' => 7],
            ],
        ]);
        $I->seeResponseCodeIs(200);
        $call = json_decode($I->grabResponse(), true);
        $I->assertFalse($call['result']['isError'] ?? true);
        $I->assertSame(true, $call['result']['structuredContent']['auto'] ?? null);
    }

    public function testMcpCallToolReturnsForbiddenWithoutScope(\FunctionalTester $I): void
    {
        $this->authorize($I, ['test.read']);

        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'demo_tool',
                'arguments' => ['value' => 1],
            ],
        ]);

        $I->seeResponseCodeIs(403);
        $response = json_decode($I->grabResponse(), true);
        $I->assertSame(-32003, $response['error']['code'] ?? null);
    }

    public function testRemoteWriteCannotBeCalledEvenWithWriteScope(\FunctionalTester $I): void
    {
        $this->authorize($I, ['test.write']);
        $I->sendPost('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'remote_write_demo_tool', 'arguments' => []],
        ]);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['result']['isError'] ?? false);
    }

    private function authorize(\FunctionalTester $I, array $scopes): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->jwt([
            'iss' => 'http://localhost',
            'aud' => 'yii2-ai-agent-tests',
            'type' => 'access',
            'sub' => 'user-1',
            'client_id' => 'client-1',
            'jti' => bin2hex(random_bytes(8)),
            'iat' => time(),
            'exp' => time() + 300,
            'scope' => implode(' ', $scopes),
        ]));
    }

    private function jwt(array $claims): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $encodedHeader = $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, 'mcp-test-secret', true);

        return $encodedHeader . '.' . $encodedPayload . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
