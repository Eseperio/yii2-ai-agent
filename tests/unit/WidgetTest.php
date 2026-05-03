<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\Module;
use eseperio\aiagent\widgets\AiChat;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Yii::$app->setModule('aiAgent', [
            'class' => Module::class,
            'defaultModel' => 'gpt-test-default',
            'enabled' => true,
            'permissions' => [
                'canViewChat' => true,
                'canCreateChat' => true,
                'canViewHistory' => true,
                'canContinueChat' => true,
                'canSendMessage' => true,
                'canRenameChat' => true,
                'canDeleteChat' => true,
                'canArchiveChat' => true,
                'canSetContext' => true,
                'canExecuteTool' => true,
                'canRenderContext' => true,
                'canUseModel' => true,
            ],
        ]);
    }

    public function testBuildPropsUsesWidgetOverridesAndModuleDefault(): void
    {
        $widget = new class extends AiChat {
            public function exposeProps(): array
            {
                return $this->buildProps($this->getModule());
            }
        };

        $widget->mode = AiChat::MODE_FLOATING;
        $widget->position = AiChat::POSITION_TOP_LEFT;
        $widget->model = 'gpt-widget';
        $widget->conversationId = null;
        $widget->contexts = [['type' => 10]];
        $widget->apiUrls = ['/api/chat'];
        $widget->autoOpen = true;
        $widget->showConversationList = false;
        $widget->conversationUrlParam = 'chat_id';
        $widget->toolsExecutedCallback = 'window.onToolsExecuted';

        $props = $widget->exposeProps();

        $this->assertSame('floating', $props['mode']);
        $this->assertSame('top-left', $props['position']);
        $this->assertSame('gpt-widget', $props['model']);
        $this->assertSame([['type' => 10]], $props['contexts']);
        $this->assertSame(['/api/chat'], $props['apiUrls']);
        $this->assertTrue($props['autoOpen']);
        $this->assertFalse($props['showConversationList']);
        $this->assertSame('chat_id', $props['conversationUrlParam']);
        $this->assertSame('window.onToolsExecuted', $props['toolsExecutedCallback']);
        $this->assertCount(20, $props['welcomeMessages']);
        $this->assertSame('Hola, ¿qué hacemos hoy?', $props['welcomeMessages'][0]);
        $this->assertTrue($props['permissions']['canViewChat']);
        $this->assertTrue($props['permissions']['canUseModel']);
    }

    public function testModuleAlternatesWelcomeMessageByConversationId(): void
    {
        $module = \Yii::$app->getModule('aiAgent');

        $this->assertSame('Hola, ¿qué hacemos hoy?', $module->resolveWelcomeMessage(1));
        $this->assertSame('Hola, ¿por dónde empezamos?', $module->resolveWelcomeMessage(2));
        $this->assertSame('Hola, ¿qué hacemos hoy?', $module->resolveWelcomeMessage(21));
    }

    public function testRunReturnsEmptyWhenModuleDisabled(): void
    {
        \Yii::$app->setModule('aiAgent', [
            'class' => Module::class,
            'enabled' => false,
        ]);

        $widget = new AiChat();
        $this->assertSame('', $widget->run());
    }
}
