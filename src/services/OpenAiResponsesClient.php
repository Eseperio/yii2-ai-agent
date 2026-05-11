<?php

namespace eseperio\aiagent\services;

use OpenAI;
use OpenAI\Client;
use GuzzleHttp\Client as GuzzleClient;
use yii\base\Component;
use yii\base\InvalidConfigException;

class OpenAiResponsesClient extends Component implements AiClientInterface
{
    public string $apiKey = '';
    public ?string $model = null;
    public ?string $organization = null;
    public ?string $project = null;
    public ?string $serviceTier = null;
    public ?float $timeout = 120.0;
    public ?float $connectTimeout = 20.0;
    public array $httpClientOptions = [];

    private ?Client $client = null;

    public function init(): void
    {
        parent::init();

        if ($this->apiKey !== '') {
            $factory = OpenAI::factory()->withApiKey($this->apiKey);
            $options = $this->httpClientOptions;
            if ($this->timeout !== null) {
                $options['timeout'] ??= $this->timeout;
            }
            if ($this->connectTimeout !== null) {
                $options['connect_timeout'] ??= $this->connectTimeout;
            }
            $factory = $factory->withHttpClient(new GuzzleClient($options));
            if ($this->organization) {
                $factory = $factory->withOrganization($this->organization);
            }
            if ($this->project) {
                $factory = $factory->withProject($this->project);
            }
            $this->client = $factory->make();
        }
    }

    public function createImageGeneration(array $payload): array
    {
        if ($this->apiKey === 'test') {
            return $this->fakeImageResponse();
        }

        return $this->requestJson('images/generations', [\GuzzleHttp\RequestOptions::JSON => $payload]);
    }

    public function createImageEdit(array $fields, array $imageFiles): array
    {
        if ($this->apiKey === 'test') {
            return $this->fakeImageResponse();
        }

        $multipart = [];
        foreach ($fields as $name => $contents) {
            $multipart[] = ['name' => (string)$name, 'contents' => (string)$contents];
        }
        foreach ($imageFiles as $file) {
            $multipart[] = [
                'name' => 'image[]',
                'contents' => fopen($file, 'rb'),
                'filename' => basename($file),
            ];
        }

        return $this->requestJson('images/edits', [\GuzzleHttp\RequestOptions::MULTIPART => $multipart]);
    }

    public function createResponse(array $payload): array
    {
        if ($this->apiKey === 'test') {
            $input = json_encode($payload['input'] ?? []);
            if (is_string($input) && str_contains($input, 'retry-previous-response-id') && !empty($payload['previous_response_id'])) {
                throw new InvalidConfigException('previous_response_id was rejected due to missing tool outputs');
            }

            return (new FakeOpenAiResponseFactory())->create($payload);
        }

        if (!$this->client instanceof Client) {
            throw new InvalidConfigException('OpenAI client is not configured.');
        }

        $response = $this->client->responses()->create($payload);
        return is_object($response) && method_exists($response, 'toArray') ? $response->toArray() : (array)$response;
    }

    private function requestJson(string $path, array $options): array
    {
        if ($this->apiKey === '') {
            throw new InvalidConfigException('OpenAI client is not configured.');
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }
        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }
        $options['headers'] = array_merge($headers, $options['headers'] ?? []);
        if ($this->timeout !== null) {
            $options['timeout'] ??= $this->timeout;
        }
        if ($this->connectTimeout !== null) {
            $options['connect_timeout'] ??= $this->connectTimeout;
        }

        $client = new GuzzleClient(array_merge(['base_uri' => 'https://api.openai.com/v1/'], $this->httpClientOptions));
        $response = $client->post($path, $options);
        $decoded = json_decode((string)$response->getBody(), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function fakeImageResponse(): array
    {
        return [
            'created' => time(),
            'data' => [[
                'b64_json' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            ]],
        ];
    }
}
