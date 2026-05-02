<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\contracts\ManualProviderInterface;
use eseperio\aiagent\dto\ManualContext;
use eseperio\aiagent\dto\ManualDefinition;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use yii\base\Component;

class ManualRegistry extends Component
{
    public function getManuals(ManualContext $context): array
    {
        $module = $this->getModule();
        $manuals = $module?->manuals ?? [];
        foreach ($module?->manualProviders ?? [] as $provider) {
            $providerInstance = is_callable($provider) || is_object($provider) ? $provider : \Yii::createObject($provider);
            if ($providerInstance instanceof ManualProviderInterface) {
                $manuals = array_merge($manuals, $providerInstance->getManuals($context));
            } elseif (is_callable($providerInstance)) {
                $provided = call_user_func($providerInstance, $context);
                if (is_array($provided)) {
                    $manuals = array_merge($manuals, $provided);
                }
            }
        }

        return array_values(array_filter($manuals, fn($manual) => $manual instanceof ManualDefinition && $this->isAvailable($manual, $context)));
    }

    public function getTools(): array
    {
        return [
            new ToolDefinition(
                'list_agent_manuals',
                'Listar manuales disponibles para conocer procedimientos antes de actuar.',
                [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['topic'],
                    'additionalProperties' => false,
                ],
                [$this, 'listManualsTool'],
                false,
                'yii2-ai-agent-manuals',
                [],
                null,
                ['readOnly' => true]
            ),
            new ToolDefinition(
                'read_agent_manual',
                'Leer un manual concreto por id para entender el procedimiento recomendado.',
                [
                    'type' => 'object',
                    'properties' => [
                        'manual_id' => ['type' => 'string'],
                    ],
                    'required' => ['manual_id'],
                    'additionalProperties' => false,
                ],
                [$this, 'readManualTool'],
                false,
                'yii2-ai-agent-manuals',
                [],
                null,
                ['readOnly' => true]
            ),
        ];
    }

    public function listManualsTool(ToolExecutionContext $context, array $arguments): ToolResult
    {
        $manualContext = $this->buildManualContext($context);
        $topic = mb_strtolower(trim((string)($arguments['topic'] ?? '')));
        $manuals = array_map(static function (ManualDefinition $manual): array {
            return [
                'id' => $manual->id,
                'title' => $manual->title,
                'summary' => $manual->summary,
                'metadata' => $manual->metadata,
            ];
        }, $this->getManuals($manualContext));

        if ($topic !== '') {
            $manuals = array_values(array_filter($manuals, static function (array $manual) use ($topic): bool {
                return str_contains(mb_strtolower($manual['id'] . ' ' . $manual['title'] . ' ' . $manual['summary']), $topic);
            }));
        }

        return new ToolResult(true, ['manuals' => $manuals], null, [], [], 'Manual list loaded');
    }

    public function readManualTool(ToolExecutionContext $context, array $arguments): ToolResult
    {
        $manualId = trim((string)($arguments['manual_id'] ?? ''));
        if ($manualId === '') {
            return new ToolResult(false, null, 'manual_id is required', [], [], 'Manual id is required');
        }

        $manualContext = $this->buildManualContext($context);
        foreach ($this->getManuals($manualContext) as $manual) {
            if ($manual->id !== $manualId) {
                continue;
            }

            $content = $manual->content;
            if ($content instanceof \Closure) {
                $content = $content($manualContext, $manual);
            }

            return new ToolResult(true, [
                'id' => $manual->id,
                'title' => $manual->title,
                'summary' => $manual->summary,
                'content' => is_array($content) ? $content : (string)$content,
                'metadata' => $manual->metadata,
            ], null, [], [], 'Manual loaded');
        }

        return new ToolResult(false, null, 'Manual not found', [], [], 'Manual not found');
    }

    private function buildManualContext(ToolExecutionContext $context): ManualContext
    {
        return new ManualContext(
            conversation: $context->conversation,
            contexts: $context->contexts ?? [],
            user: $context->user,
            request: $context->request,
            model: null,
            metadata: $context->metadata ?? []
        );
    }

    private function isAvailable(ManualDefinition $manual, ManualContext $context): bool
    {
        if (is_bool($manual->available)) {
            return $manual->available;
        }

        if (is_callable($manual->available)) {
            return (bool)call_user_func($manual->available, $context, $manual);
        }

        if (!empty($manual->contextTypes)) {
            foreach ($context->contexts as $activeContext) {
                $type = is_object($activeContext) ? ($activeContext->type ?? null) : ($activeContext['type'] ?? null);
                if (in_array((int)$type, array_map('intval', $manual->contextTypes), true)) {
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
