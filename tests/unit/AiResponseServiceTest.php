<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\Module;
use eseperio\aiagent\services\AiResponseService;
use eseperio\aiagent\services\OpenAiResponsesClient;
use PHPUnit\Framework\TestCase;

class AiInstructionProviderStub implements \eseperio\aiagent\contracts\InstructionProviderInterface
{
    public mixed $available = null;

    public function buildInstructions(\eseperio\aiagent\dto\InstructionContext $context): string
    {
        return 'third:' . $context->model;
    }
}

class AiInstructionProviderWithoutAvailableStub implements \eseperio\aiagent\contracts\InstructionProviderInterface
{
    public function buildInstructions(\eseperio\aiagent\dto\InstructionContext $context): string
    {
        return 'without-available:' . $context->model;
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

    public function createTranscription(array $fields, string $filePath, ?string $fileName = null): array
    {
        return ['text' => 'captured transcription'];
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
        \Yii::$app->getModule('aiAgent')->baseInstructions = 'base contract';
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
        $this->assertStringStartsWith('base contract', $result);
        $this->assertStringContainsString('first', $result);
        $this->assertStringContainsString('second:1', $result);
        $this->assertStringContainsString('third:gpt-test', $result);
    }

    public function testBuildInstructionsIncludesApplicationContextOnce(): void
    {
        $module = \Yii::$app->getModule('aiAgent');
        $module->baseInstructions = 'base contract';
        $module->applicationContext = static function ($context): string {
            return 'This app sells configurable print products. Model: ' . $context->model;
        };
        $module->applicationContextMaxLength = 48;

        $service = new AiResponseService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('buildInstructions');
        $method->setAccessible(true);

        $result = $method->invoke($service, $module, [
            'model' => 'gpt-test',
            'input' => [['role' => 'user', 'content' => 'hello']],
        ]);

        $this->assertStringContainsString('base contract', $result);
        $this->assertStringContainsString('Application context:', $result);
        $this->assertStringContainsString('This app sells configurable print products.', $result);
        $this->assertStringNotContainsString('Model: gpt-test', $result);
    }

    public function testBuildInstructionsIgnoresEmptyOptionalParts(): void
    {
        $module = \Yii::$app->getModule('aiAgent');
        $module->baseInstructions = '';
        $module->applicationContext = null;
        $module->instructionProviders = [
            static fn(): ?string => null,
            static fn(): string => '  usable instructions  ',
        ];

        $service = new AiResponseService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('buildInstructions');
        $method->setAccessible(true);

        $result = $method->invoke($service, $module, [
            'model' => 'gpt-test',
            'input' => [['role' => 'user', 'content' => 'hello']],
        ]);

        $this->assertSame('usable instructions', $result);
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

        $this->assertStringContainsString('third:gpt-test', $result);
        $this->assertStringNotContainsString('first', $result);
    }

    public function testBuildInstructionsDoesNotPassAvailableToProviderConfig(): void
    {
        \Yii::$app->getModule('aiAgent')->instructionProviders = [
            [
                'available' => static fn($context): bool => true,
                'class' => AiInstructionProviderWithoutAvailableStub::class,
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

        $this->assertStringContainsString('without-available:gpt-test', $result);
    }

    public function testSendRetriesWithoutPreviousResponseIdWhenRejected(): void
    {
        CapturingAiClient::$payloads = [];
        \Yii::$app->getModule('aiAgent')->baseInstructions = 'base fallback';
        \Yii::$app->getModule('aiAgent')->applicationContext = 'fallback app context';
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
        $this->assertStringContainsString('Hard invariants for this turn', CapturingAiClient::$payloads[0]['instructions'] ?? '');
        $this->assertStringNotContainsString('fallback app context', CapturingAiClient::$payloads[0]['instructions'] ?? '');
        $this->assertStringContainsString('base fallback', CapturingAiClient::$payloads[1]['instructions'] ?? '');
        $this->assertStringContainsString('fallback app context', CapturingAiClient::$payloads[1]['instructions'] ?? '');
    }

    public function testSendCanUseFullInstructionsOnContinuationWhenConfigured(): void
    {
        CapturingAiClient::$payloads = [];
        $module = \Yii::$app->getModule('aiAgent');
        $module->baseInstructions = 'base full continuation';
        $module->applicationContext = 'full app context';
        $module->useCompactContinuationInstructions = false;
        $module->set('clientFactory', [
            'class' => CapturingAiClientFactory::class,
        ]);

        $service = new AiResponseService();
        $service->send([
            'model' => 'gpt-test',
            'previous_response_id' => 'prev_123',
            'input' => [
                ['role' => 'user', 'content' => 'retry-previous-response-id'],
            ],
        ]);

        $this->assertStringContainsString('base full continuation', CapturingAiClient::$payloads[0]['instructions'] ?? '');
        $this->assertStringContainsString('full app context', CapturingAiClient::$payloads[0]['instructions'] ?? '');
    }

    public function testSendAddsServiceTierAndDoesNotForwardInternalContexts(): void
    {
        CapturingAiClient::$payloads = [];
        \Yii::$app->getModule('aiAgent')->clientConfig = [
            'apiKey' => 'test',
            'serviceTier' => 'flex',
        ];
        \Yii::$app->getModule('aiAgent')->set('clientFactory', [
            'class' => CapturingAiClientFactory::class,
        ]);

        $service = new AiResponseService();
        $service->send([
            'model' => 'gpt-test',
            'input' => [
                ['role' => 'user', 'content' => 'hello'],
            ],
            'contexts' => [
                ['type' => 10, 'metadata' => ['id' => 1]],
            ],
        ]);

        $this->assertSame('flex', CapturingAiClient::$payloads[0]['service_tier'] ?? null);
        $this->assertArrayNotHasKey('contexts', CapturingAiClient::$payloads[0]);
    }

    public function testSendDoesNotAddRoleToFunctionCallOutputItems(): void
    {
        CapturingAiClient::$payloads = [];
        \Yii::$app->getModule('aiAgent')->set('clientFactory', [
            'class' => CapturingAiClientFactory::class,
        ]);

        $service = new AiResponseService();
        $service->send([
            'model' => 'gpt-test',
            'input' => [
                [
                    'type' => 'function_call_output',
                    'call_id' => 'call_123',
                    'output' => '{"success":true}',
                ],
            ],
        ]);

        $this->assertSame('function_call_output', CapturingAiClient::$payloads[0]['input'][0]['type'] ?? null);
        $this->assertArrayNotHasKey('role', CapturingAiClient::$payloads[0]['input'][0]);
    }

    public function testSendAddsDefaultStructuredResponseFormat(): void
    {
        CapturingAiClient::$payloads = [];
        \Yii::$app->getModule('aiAgent')->set('clientFactory', [
            'class' => CapturingAiClientFactory::class,
        ]);

        $service = new AiResponseService();
        $service->send([
            'model' => 'gpt-test',
            'input' => [
                ['role' => 'user', 'content' => 'hello'],
            ],
        ]);

        $format = CapturingAiClient::$payloads[0]['text']['format'] ?? null;
        $this->assertIsArray($format);
        $this->assertSame('json_schema', $format['type'] ?? null);
        $this->assertSame('ai_agent_response', $format['name'] ?? null);
        $this->assertArrayHasKey('questionnaire', $format['schema']['properties'] ?? []);
    }

    public function testOpenAiClientAcceptsServiceTierConfig(): void
    {
        $client = new OpenAiResponsesClient([
            'apiKey' => 'test',
            'serviceTier' => 'flex',
        ]);

        $this->assertSame('flex', $client->serviceTier);
    }
}
