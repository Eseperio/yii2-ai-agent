<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\dto\ContextRenderContext;
use eseperio\aiagent\models\Context;
use yii\base\Component;

class ContextRenderer extends Component
{
    public function render(Context $context, ContextRenderContext $renderContext): array
    {
        $module = $this->getModule();
        $renderer = $module?->contextRenderers[$context->type] ?? null;

        if (is_callable($renderer)) {
            $data = call_user_func($renderer, $context, $renderContext);
            if (is_array($data)) {
                return $this->normalize($context, $data);
            }
        } elseif (is_string($renderer) && class_exists($renderer)) {
            $instance = \Yii::createObject($renderer);
            if (method_exists($instance, 'render')) {
                $data = $instance->render($context, $renderContext);
                if (is_array($data)) {
                    return $this->normalize($context, $data);
                }
            }
        } elseif (is_array($renderer) && isset($renderer['class'])) {
            $instance = \Yii::createObject($renderer);
            if (method_exists($instance, 'render')) {
                $data = $instance->render($context, $renderContext);
                if (is_array($data)) {
                    return $this->normalize($context, $data);
                }
            }
        }

        $title = $context->label ?: 'Context #' . $context->id;
        return [
            'type' => $context->type,
            'id' => $context->id,
            'type_label' => (string)$context->type,
            'title' => $title,
            'excerpt' => null,
            'image_url' => null,
            'action_url' => null,
            'badges' => [],
            'locked' => false,
            'can_change' => true,
        ];
    }

    public function resolveFingerprint(array $contexts): string
    {
        return sha1(json_encode($this->normalizeContextsForFingerprint($contexts)));
    }

    public function normalizeContextsForFingerprint(array $contexts): array
    {
        $normalized = [];
        foreach ($contexts as $context) {
            $type = is_object($context) ? ($context->type ?? null) : ($context['type'] ?? null);
            $metadata = is_object($context) ? ($context->metadata ?? null) : ($context['metadata'] ?? null);
            $metadataArray = $this->normalizeMetadata($metadata);
            $normalized[] = [
                'type' => (int)($type ?? 0),
                'metadata' => $metadataArray,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            return [$left['type'], json_encode($left['metadata'])] <=> [$right['type'], json_encode($right['metadata'])];
        });

        return $normalized;
    }

    private function normalize(Context $context, array $data): array
    {
        return array_merge([
            'type' => $context->type,
            'id' => $context->id,
            'type_label' => (string)$context->type,
            'title' => $context->label ?: 'Context #' . $context->id,
            'excerpt' => null,
            'image_url' => null,
            'action_url' => null,
            'badges' => [],
            'locked' => false,
            'can_change' => true,
        ], $data);
    }

    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($metadata)) {
            return [];
        }

        ksort($metadata);
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = $this->normalizeMetadata($value);
            }
        }

        return $metadata;
    }

    private function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
