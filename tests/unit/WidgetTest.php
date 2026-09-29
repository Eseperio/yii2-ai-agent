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
        $widget->showNewConversationButton = false;
        $widget->showWelcomeMessage = false;
        $widget->conversationUrlParam = 'chat_id';
        $widget->workspaceUrl = '/assistant/workspace';
        $widget->assistantTitle = 'Guided assistant';
        $widget->conversationTitle = 'Create a product';
        $widget->conversationMetadata = ['flow' => 'product_creation'];
        $widget->starterMessage = 'Start the interview.';
        $widget->inputPlaceholder = 'Describe the product';
        $widget->collapsedHint = 'Continue editing with AI';
        $widget->toolsExecutedCallback = 'window.onToolsExecuted';

        $props = $widget->exposeProps();

        $this->assertSame('floating', $props['mode']);
        $this->assertSame('top-left', $props['position']);
        $this->assertSame('gpt-widget', $props['model']);
        $this->assertSame([['type' => 10]], $props['contexts']);
        $this->assertSame(['/api/chat'], $props['apiUrls']);
        $this->assertTrue($props['autoOpen']);
        $this->assertFalse($props['showConversationList']);
        $this->assertFalse($props['showNewConversationButton']);
        $this->assertSame('chat_id', $props['conversationUrlParam']);
        $this->assertSame('/assistant/workspace', $props['workspaceUrl']);
        $this->assertSame('Guided assistant', $props['assistantTitle']);
        $this->assertSame('Create a product', $props['conversationTitle']);
        $this->assertSame(['flow' => 'product_creation'], $props['conversationMetadata']);
        $this->assertSame('Start the interview.', $props['starterMessage']);
        $this->assertSame('Describe the product', $props['inputPlaceholder']);
        $this->assertSame('Continue editing with AI', $props['collapsedHint']);
        $this->assertSame('window.onToolsExecuted', $props['toolsExecutedCallback']);
        $this->assertSame([], $props['welcomeMessages']);
        $this->assertTrue($props['permissions']['canViewChat']);
        $this->assertTrue($props['permissions']['canUseModel']);
    }

    public function testConversationListIsOnlyExposedInPageMode(): void
    {
        $widget = new class extends AiChat {
            public function exposeProps(): array
            {
                return $this->buildProps($this->getModule());
            }
        };

        $widget->mode = AiChat::MODE_FLOATING;
        $widget->showConversationList = true;
        $this->assertFalse($widget->exposeProps()['showConversationList']);

        $widget->mode = AiChat::MODE_PAGE;
        $this->assertTrue($widget->exposeProps()['showConversationList']);
        $this->assertStringContainsString('aiAgent', $widget->exposeProps()['workspaceUrl']);
        $this->assertStringContainsString('chat', $widget->exposeProps()['workspaceUrl']);
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
