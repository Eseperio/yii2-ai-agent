<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\ToolContext;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\contracts\ToolProviderInterface;
use eseperio\aiagent\services\ImageToolProvider;
use yii\base\Component;

class ToolRegistry extends Component
{
    public function getTools(ToolContext $context): array
    {
        $module = $this->getModule();
        $tools = $module?->tools ?? [];
        if ($module?->imageToolsEnabled) {
            $tools = array_merge($tools, (new ImageToolProvider())->getTools($context));
        }
        foreach ($module?->toolProviders ?? [] as $provider) {
            $providerInstance = is_callable($provider) || is_object($provider) ? $provider : \Yii::createObject($provider);
            if ($providerInstance instanceof ToolProviderInterface) {
                $tools = array_merge($tools, $providerInstance->getTools($context));
            } elseif (is_callable($providerInstance)) {
                $provided = call_user_func($providerInstance, $context);
                if (is_array($provided)) {
                    $tools = array_merge($tools, $provided);
                }
            }
        }
        if ($module && ($module->manuals || $module->manualProviders)) {
            $tools = array_merge($tools, $module->getManualRegistry()->getTools());
        }
        return array_values(array_filter($tools, fn($tool) => $this->isAvailable($tool, $context)));
    }

    public function normalize(array $tools): array
    {
        $normalized = [];
        $seen = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ToolDefinition) {
                if (isset($seen[$tool->name])) {
                    throw new \RuntimeException('Duplicate tool name: ' . $tool->name);
                }
                $seen[$tool->name] = true;
                $normalized[] = [
                    'type' => 'function',
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                    'strict' => (bool)($tool->metadata['strict'] ?? true),
                ];
                continue;
            }
            if (is_array($tool) && isset($tool['type'])) {
                $name = $tool['name'] ?? ($tool['function']['name'] ?? null);
                if (is_string($name)) {
                    if (isset($seen[$name])) {
                        throw new \RuntimeException('Duplicate tool name: ' . $name);
                    }
                    $seen[$name] = true;
                }
                if (isset($tool['function']) && is_array($tool['function'])) {
                    $tool = array_merge(['type' => $tool['type']], $tool['function']);
                }
                if (($tool['type'] ?? null) === 'function') {
                    $tool['strict'] ??= true;
                }
                $normalized[] = $tool;
            }
        }
        return $normalized;
    }

    public function findByName(string $name, ToolContext $context): ?ToolDefinition
    {
        foreach ($this->getTools($context) as $tool) {
            if ($tool instanceof ToolDefinition && $tool->name === $name) {
                return $tool;
            }
        }
        return null;
    }

    public function findResolvedByName(string $name, ToolContext $context): ?ToolDefinition
    {
        $matches = [];
        foreach ($this->getTools($context) as $tool) {
            if ($tool instanceof ToolDefinition && $tool->name === $name) {
                $matches[] = $tool;
            }
        }

        if (count($matches) > 1) {
            throw new \RuntimeException('Ambiguous tool name: ' . $name);
        }

        return $matches[0] ?? null;
    }

    public function getResolvedTools(ToolContext $context): array
    {
        return $this->getTools($context);
    }

    private function isAvailable(mixed $tool, ToolContext $context): bool
    {
        if (!$tool instanceof ToolDefinition) {
            return true;
        }

        if (is_bool($tool->available)) {
            return $tool->available;
        }

        if (is_callable($tool->available)) {
            return (bool)call_user_func($tool->available, $context, $tool);
        }

        if (!empty($tool->contextTypes)) {
            foreach ($context->contexts as $activeContext) {
                $type = is_object($activeContext) ? ($activeContext->type ?? null) : ($activeContext['type'] ?? null);
                if (in_array($type, $tool->contextTypes, true)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
