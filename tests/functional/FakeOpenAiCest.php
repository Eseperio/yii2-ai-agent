<?php

namespace eseperio\aiagent\tests\functional;

class FakeOpenAiCest
{
    public function testFakeEndpointWorks(\FunctionalTester $I): void
    {
        $I->sendPost('/fake-openai/v1/responses', [
            'input' => [],
        ]);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertIsArray($response);
        $I->assertArrayHasKey('id', $response);
    }

    public function testFakeEndpointCanReturnToolCall(\FunctionalTester $I): void
    {
        $I->sendPost('/fake-openai/v1/responses', [
            'input' => [
                ['role' => 'user', 'content' => 'tool-call'],
            ],
        ]);

        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertSame('completed', $response['status'] ?? null);
        $I->assertNotEmpty($response['output'][0]['content'] ?? []);
    }
}
