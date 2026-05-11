<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\contracts\ToolProviderInterface;
use eseperio\aiagent\dto\ToolContext;
use eseperio\aiagent\dto\ToolDefinition;
use eseperio\aiagent\dto\ToolExecutionContext;
use eseperio\aiagent\dto\ToolResult;
use eseperio\aiagent\models\MessageAsset;

class ImageToolProvider implements ToolProviderInterface
{
    public function getTools(ToolContext $context): array
    {
        return [
            new ToolDefinition(
                'generate_image',
                'Generate an image and store it as a chat asset. This does not attach it to business entities.',
                [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['prompt'],
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                ],
                fn(ToolExecutionContext $executionContext, array $arguments): ToolResult => $this->generate($executionContext, $arguments),
                false,
                'ai-agent-assets',
                [],
                null,
                ['effect' => 'preview', 'riskLevel' => 'low', 'allowAutonomous' => true, 'internalAssetTool' => true]
            ),
            new ToolDefinition(
                'edit_image',
                'Edit or create an image using chat image assets as references. Stores the result as a chat asset.',
                [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['prompt', 'reference_asset_ids'],
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                        'reference_asset_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    ],
                ],
                fn(ToolExecutionContext $executionContext, array $arguments): ToolResult => $this->edit($executionContext, $arguments),
                false,
                'ai-agent-assets',
                [],
                null,
                ['effect' => 'preview', 'riskLevel' => 'low', 'allowAutonomous' => true, 'internalAssetTool' => true]
            ),
        ];
    }

    private function generate(ToolExecutionContext $context, array $arguments): ToolResult
    {
        $prompt = trim((string)($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            return new ToolResult(false, null, 'Prompt vacío.', [], [], 'No se pudo generar la imagen porque falta el prompt.');
        }
        $asset = $this->module()->getImageGenerationService()->generate($prompt, $this->metadata($context));
        $this->persistAssetMessage($context, $asset, 'Imagen generada.');
        return new ToolResult(true, ['asset' => $asset->toDisplayArray()], null, [], [], 'Imagen generada.');
    }

    private function edit(ToolExecutionContext $context, array $arguments): ToolResult
    {
        $prompt = trim((string)($arguments['prompt'] ?? ''));
        $referenceAssetIds = is_array($arguments['reference_asset_ids'] ?? null) ? $arguments['reference_asset_ids'] : [];
        if ($prompt === '') {
            return new ToolResult(false, null, 'Prompt vacío.', [], [], 'No se pudo editar la imagen porque falta el prompt.');
        }
        $asset = $this->module()->getImageGenerationService()->edit($prompt, $referenceAssetIds, $this->metadata($context));
        $this->persistAssetMessage($context, $asset, 'Imagen editada.');
        return new ToolResult(true, ['asset' => $asset->toDisplayArray()], null, [], [], 'Imagen editada.');
    }

    private function persistAssetMessage(ToolExecutionContext $context, \eseperio\aiagent\models\Asset $asset, string $content): void
    {
        $conversationId = (int)($context->conversation?->id ?? 0);
        if ($conversationId <= 0) {
            return;
        }
        $message = $this->module()->getConversationManager()->addMessage(
            $conversationId,
            'assistant',
            'asset',
            $content,
            $context->responseId,
            $context->toolCallId,
            null,
            ['asset_id' => (int)$asset->id]
        );
        $this->module()->getConversationManager()->linkAssetsToMessage((int)$message->id, [(int)$asset->id], MessageAsset::USAGE_OUTPUT);
    }

    private function metadata(ToolExecutionContext $context): array
    {
        return [
            'conversation_id' => (int)($context->conversation?->id ?? 0),
            'response_id' => $context->responseId,
            'tool_call_id' => $context->toolCallId,
        ];
    }

    private function module(): \eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
