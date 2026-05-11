<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\models\Asset;
use yii\base\Component;
use yii\base\InvalidArgumentException;

class ImageGenerationService extends Component
{
    public string $model = 'gpt-image-2';
    public string $quality = 'low';
    public string $size = '1536x1024';
    public string $outputFormat = 'png';

    public function generate(string $prompt, array $metadata = []): Asset
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'quality' => $this->quality,
            'size' => $this->size,
            'output_format' => $this->outputFormat,
        ];

        $response = $this->clientCall('createImageGeneration', [$payload]);
        return $this->assetFromResponse($response, $metadata + ['prompt' => $prompt, 'operation' => 'generate']);
    }

    public function edit(string $prompt, array $referenceAssetIds = [], array $metadata = []): Asset
    {
        $references = $this->findImageAssets($referenceAssetIds);
        if ($references === []) {
            return $this->generate($prompt, $metadata + ['operation' => 'edit_without_references']);
        }

        $fields = [
            'model' => $this->model,
            'prompt' => $prompt,
            'quality' => $this->quality,
            'size' => $this->size,
            'output_format' => $this->outputFormat,
        ];
        $files = [];
        foreach ($references as $asset) {
            $files[] = $this->temporaryAssetFile($asset);
        }

        try {
            $response = $this->clientCall('createImageEdit', [$fields, $files]);
        } finally {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        return $this->assetFromResponse($response, $metadata + [
            'prompt' => $prompt,
            'operation' => 'edit',
            'reference_asset_ids' => array_map(static fn(Asset $asset): int => (int)$asset->id, $references),
        ]);
    }

    private function assetFromResponse(array $response, array $metadata): Asset
    {
        $item = $response['data'][0] ?? null;
        $b64 = is_array($item) ? ($item['b64_json'] ?? $item['base64'] ?? null) : null;
        if (!is_string($b64) || $b64 === '') {
            throw new InvalidArgumentException('La respuesta de generación de imagen no contiene base64.');
        }

        $contents = base64_decode($b64, true);
        if ($contents === false) {
            throw new InvalidArgumentException('La imagen generada no se pudo decodificar.');
        }

        return $this->module()->getAssetService()->createFromContents(
            $contents,
            'generated-' . date('Ymd-His') . '.' . $this->outputFormat,
            'image/' . ($this->outputFormat === 'jpg' ? 'jpeg' : $this->outputFormat),
            Asset::SOURCE_GENERATED,
            $metadata + ['model' => $this->model, 'quality' => $this->quality, 'size' => $this->size]
        );
    }

    private function clientCall(string $method, array $arguments): array
    {
        $client = $this->module()->getClientFactory()->create($this->module()->clientConfig);
        if (!method_exists($client, $method)) {
            throw new InvalidArgumentException('El cliente AI no soporta ' . $method . '.');
        }
        $response = $client->{$method}(...$arguments);
        return is_array($response) ? $response : (array)$response;
    }

    private function findImageAssets(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $class = $this->module()->assetClass;
        return $class::find()->where(['id' => $ids, 'type' => Asset::TYPE_IMAGE])->all();
    }

    private function temporaryAssetFile(Asset $asset): string
    {
        $extension = pathinfo($asset->filename, PATHINFO_EXTENSION) ?: 'png';
        return $this->module()->getAssetStorage()->temporaryPath($asset->storage_path, $extension);
    }

    private function module(): \eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
