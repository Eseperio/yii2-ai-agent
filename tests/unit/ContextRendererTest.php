<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\ContextRenderContext;
use eseperio\aiagent\models\Context;
use eseperio\aiagent\services\ContextRenderer;
use PHPUnit\Framework\TestCase;

class ContextArrayRendererStub
{
    public function render(Context $context, ContextRenderContext $renderContext): array
    {
        return [
            'title' => 'Array ' . $context->label,
            'excerpt' => 'array',
        ];
    }
}

class ContextRendererTest extends TestCase
{
    public function testRenderReturnsStructuredArray(): void
    {
        $renderer = new ContextRenderer();
        $context = new Context();
        $context->id = 10;
        $context->type = 5;
        $context->label = 'Demo';

        $data = $renderer->render($context, new ContextRenderContext());

        $this->assertSame('Demo', $data['title']);
        $this->assertSame(5, $data['type']);
    }

    public function testRenderResolvesCallableAndArrayRenderers(): void
    {
        $renderer = new ContextRenderer();
        $context = new Context();
        $context->id = 11;
        $context->type = 7;
        $context->label = 'Renderer demo';

        \Yii::$app->setModule('aiAgent', [
            'class' => \eseperio\aiagent\Module::class,
            'contextRenderers' => [
                7 => static function (Context $context, ContextRenderContext $renderContext): array {
                    return [
                        'title' => 'Callable ' . $context->label,
                        'excerpt' => 'callable',
                    ];
                },
                8 => [
                    'class' => ContextArrayRendererStub::class,
                ],
            ],
        ]);

        $callableData = $renderer->render($context, new ContextRenderContext());
        $this->assertSame('Callable Renderer demo', $callableData['title']);
        $this->assertSame('callable', $callableData['excerpt']);

        $otherContext = new Context();
        $otherContext->id = 12;
        $otherContext->type = 8;
        $otherContext->label = 'Renderer demo 2';
        $arrayData = $renderer->render($otherContext, new ContextRenderContext());
        $this->assertSame('Array Renderer demo 2', $arrayData['title']);
        $this->assertSame('array', $arrayData['excerpt']);
    }
}
