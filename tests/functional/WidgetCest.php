<?php

namespace eseperio\aiagent\tests\functional;

class WidgetCest
{
    public function testWidgetInPageRendersMountPoint(\FunctionalTester $I): void
    {
        $I->sendGet('/ai-agent/chat/index');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('ai-agent-widget');
        $I->seeResponseContains('data-props');
        $I->seeResponseContains('ai-chat.css');
        $I->seeResponseContains('ai-chat.js');
    }

    public function testFloatingWidgetIncludesPositionAndModel(\FunctionalTester $I): void
    {
        $I->sendGet('/ai-agent/chat/index?mode=floating&position=top-left&model=gpt-widget');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('top-left');
        $I->seeResponseContains('gpt-widget');
    }

    public function testWidgetModelDeniedReturns403(\FunctionalTester $I): void
    {
        $I->sendGet('/ai-agent/chat/index?model=gpt-denied&deny_model=1');
        $I->seeResponseCodeIs(403);
    }

    public function testCreateConversationRespectsDeniedModel(\FunctionalTester $I): void
    {
        $I->sendPost('/ai-agent/chat/create-conversation?deny_model=1', [
            'model' => 'gpt-denied',
        ]);
        $I->seeResponseCodeIs(403);
    }
}
