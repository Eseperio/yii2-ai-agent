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
}
