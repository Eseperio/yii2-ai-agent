<?php

namespace eseperio\aiagent\services;

use yii\base\Component;

class AiResponseService extends Component
{
    public function send(array $payload): array
    {
        $module = $this->getModule();
        if (!$module) {
            return [];
        }
        $internalContexts = $payload['contexts'] ?? [];

        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $payload['metadata'] = $this->normalizeMetadata($payload['metadata']);
        }

        if (!isset($payload['service_tier'])) {
            $serviceTier = $module->serviceTier ?: ($module->clientConfig['serviceTier'] ?? null);
            if (is_string($serviceTier) && trim($serviceTier) !== '') {
                $payload['service_tier'] = trim($serviceTier);
            }
        }

        if (isset($payload['input']) && is_array($payload['input'])) {
            $payload['input'] = $this->normalizeInput($payload['input']);
        }

        if (isset($payload['tools']) && is_array($payload['tools'])) {
            $payload['tools'] = $module->getToolRegistry()->normalize($payload['tools']);
        }

        if (!isset($payload['text']) && is_array($module->responseTextFormat)) {
            $payload['text'] = ['format' => $module->responseTextFormat];
        }

        if (!isset($payload['tools'])) {
            $tools = $module->getToolRegistry()->getTools(new \eseperio\aiagent\dto\ToolContext(
                conversation: null,
                contexts: [],
                user: null,
                request: null,
                model: $payload['model'] ?? null,
                metadata: $payload['metadata'] ?? []
            ));
            $payload['tools'] = $module->getToolRegistry()->normalize($tools);
        }

        if (!isset($payload['instructions']) && !isset($payload['previous_response_id'])) {
            $payload['instructions'] = $this->buildInstructions($module, $payload);
        }

        unset($payload['contexts']);

        $client = $module->getClientFactory()->create($module->clientConfig);
        try {
            return $client->createResponse($payload);
        } catch (\Throwable $e) {
            if (!isset($payload['previous_response_id'])) {
                throw $e;
            }

            $fallbackPayload = $payload;
            unset($fallbackPayload['previous_response_id']);
            if (!isset($fallbackPayload['instructions'])) {
                $instructionPayload = $fallbackPayload;
                $instructionPayload['contexts'] = $internalContexts;
                $fallbackPayload['instructions'] = $this->buildInstructions($module, $instructionPayload);
            }

            return $client->createResponse($fallbackPayload);
        }
    }

    private function buildInstructions(\eseperio\aiagent\Module $module, array $payload): ?string
    {
        $contexts = $payload['contexts'] ?? [];
        $instructionContext = new \eseperio\aiagent\dto\InstructionContext(
            conversation: null,
            messages: $payload['input'] ?? [],
            contexts: $contexts,
            user: null,
            request: null,
            model: $payload['model'] ?? null,
            metadata: $payload['metadata'] ?? []
        );

        $parts = [];
        if (trim($module->baseInstructions) !== '') {
            $parts[] = $module->baseInstructions;
        }
        if (method_exists($module, 'buildApplicationContextInstructions')) {
            $parts[] = $module->buildApplicationContextInstructions($instructionContext);
        }
        foreach ($module->instructionProviders as $provider) {
            if (!$this->isInstructionProviderAvailable($provider, $instructionContext)) {
                continue;
            }
            $providerInstance = $this->resolveProvider($provider);
            if (is_callable($providerInstance)) {
                $parts[] = (string)call_user_func($providerInstance, $instructionContext);
                continue;
            }
            if ($providerInstance instanceof \eseperio\aiagent\contracts\InstructionProviderInterface) {
                $parts[] = $providerInstance->buildInstructions($instructionContext);
            }
        }

        $parts = array_values(array_filter(array_map('trim', $parts)));
        return $parts ? implode("\n\n", $parts) : null;
    }

    private function isInstructionProviderAvailable(mixed $provider, \eseperio\aiagent\dto\InstructionContext $context): bool
    {
        if (is_array($provider) && array_key_exists('available', $provider)) {
            $available = $provider['available'];
            if (is_bool($available)) {
                return $available;
            }
            if (is_callable($available)) {
                return (bool)call_user_func($available, $context);
            }
        }

        if (is_object($provider) && property_exists($provider, 'available')) {
            $available = $provider->available;
            if (is_bool($available)) {
                return $available;
            }
            if (is_callable($available)) {
                return (bool)call_user_func($available, $context);
            }
        }

        return true;
    }

    private function resolveProvider(mixed $provider): mixed
    {
        if (is_callable($provider) || is_object($provider)) {
            return $provider;
        }

        if (is_array($provider)) {
            unset($provider['available']);
            return \Yii::createObject($provider);
        }

        if (is_string($provider) && class_exists($provider)) {
            return \Yii::createObject($provider);
        }

        return $provider;
    }

    private function normalizeInput(array $input): array
    {
        $normalized = [];
        foreach ($input as $item) {
            if (!is_array($item)) {
                continue;
            }
            $content = $item['content'] ?? null;
            if (is_array($content)) {
                $content = array_map(static function ($chunk) {
                    if (!is_array($chunk)) {
                        return $chunk;
                    }
                    if (isset($chunk['text'])) {
                        $chunk['text'] = (string)$chunk['text'];
                    }
                    return $chunk;
                }, $content);
            } elseif (!is_null($content)) {
                $content = (string)$content;
            }
            $normalized[] = array_merge($item, [
                'role' => isset($item['role']) ? (string)$item['role'] : 'user',
                'content' => $content,
            ]);
        }
        return $normalized;
    }

    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];
        foreach ($metadata as $key => $value) {
            $normalized[(string)$key] = is_scalar($value) || $value === null ? (string)$value : json_encode($value);
        }
        return $normalized;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
