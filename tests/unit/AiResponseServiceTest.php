<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\Module;
use eseperio\aiagent\services\AiResponseService;
use PHPUnit\Framework\TestCase;

class AiInstructionProviderStub implements \eseperio\aiagent\contracts\InstructionProviderInterface
{
    public mixed $available = null;

    public function buildInstructions(\eseperio\aiagent\dto\InstructionContext $context): string
    {
        return 'third:' . $context->model;
    }
}

class CapturingAiClient implements \eseperio\aiagent\services\AiClientInterface
{
    public static array $payloads = [];

    public function createResponse(array $payload): array
    {
        self::$payloads[] = $payload;

        if (isset($payload['previous_response_id'])) {
            throw new \RuntimeException('previous response not supported');
        }

        return [
            'id' => 'captured_resp',
            'status' => 'completed',
        ];
    }
}

class CapturingAiClientFactory extends \eseperio\aiagent\services\AiClientFactory
{
    public function create(array $config = []): \eseperio\aiagent\services\AiClientInterface
    {
        return new CapturingAiClient();
    }
}

class AiResponseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Yii::$app->setModule('aiAgent', [
            'class' => Module::class,
            'clientConfig' => ['apiKey' => 'test'],
            'enabled' => true,
            'permissions' => [
                'canViewChat' => true,
                'canUseModel' => true,
            ],
        ]);
    }

    public function testBuildInstructionsCombinesProvidersInOrder(): void
    {
        $captured = [];
        \Yii::$app->getModule('aiAgent')->instructionProviders = [
            static function ($context) use (&$captured): string {
                $captured[] = $context->model;
                return 'first';
            },
            new class {
                public function __invoke($context): string
                {
                    return 'second:' . count($context->messages);
                }
            },
            AiInstructionProviderStub::class,
        ];

        $service = new AiResponseService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('buildInstructions');
        $method->setAccessible(true);

        $result = $method->invoke($service, \Yii::$app->getModule('aiAgent'), [
            'model' => 'gpt-test',
            'input' => [['role' => 'user', 'content' => 'hello']],
        ]);

        $this->assertSame('gpt-test', $captured[0]);
        $this->assertStringContainsString('first', $result);
        $this->assertStringContainsString('second:1', $result);
        $this->assertStringContainsString('third:gpt-test', $result);
    }

    public function testBuildInstructionsSkipsUnavailableProviders(): void
    {
        \Yii::$app->getModule('aiAgent')->instructionProviders = [
            [
                'available' => static fn($context): bool => false,
                'class' => AiInstructionProviderStub::class,
            ],
            [
                'available' => static fn($context): bool => true,
                'class' => AiInstructionProviderStub::class,
            ],
        ];

        $service = new AiResponseService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('buildInstructions');
        $method->setAccessible(true);

        $result = $method->invoke($service, \Yii::$app->getModule('aiAgent'), [
            'model' => 'gpt-test',
            'input' => [],
        ]);

        $this->assertSame('third:gpt-test', $result);
    }

    public function testSendRetriesWithoutPreviousResponseIdWhenRejected(): void
    {
        CapturingAiClient::$payloads = [];
        \Yii::$app->getModule('aiAgent')->set('clientFactory', [
            'class' => CapturingAiClientFactory::class,
        ]);

        $service = new AiResponseService();
        $response = $service->send([
            'model' => 'gpt-test',
            'previous_response_id' => 'prev_123',
            'input' => [
                ['role' => 'user', 'content' => 'retry-previous-response-id'],
            ],
        ]);

        $this->assertSame('completed', $response['status'] ?? null);
        $this->assertSame('captured_resp', $response['id'] ?? null);
        $this->assertCount(2, CapturingAiClient::$payloads);
        $this->assertArrayNotHasKey('previous_response_id', CapturingAiClient::$payloads[1]);
        $this->assertArrayNotHasKey('instructions', CapturingAiClient::$payloads[0]);
    }
}
