<?php

namespace eseperio\aiagent\services;

use eseperio\aiagent\models\Asset;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class AssetService extends Component
{
    public int $maxUploadSize = 52428800;
    public array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function createFromUpload(UploadedFile $file, array $metadata = []): Asset
    {
        if ($file->size > $this->maxUploadSize) {
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido.');
        }

        $mimeType = $this->detectMimeType($file->tempName, $file->type);
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Tipo de archivo no permitido: ' . $mimeType);
        }

        $contents = file_get_contents($file->tempName);
        if ($contents === false) {
            throw new InvalidArgumentException('No se pudo leer el archivo subido.');
        }

        return $this->createFromContents($contents, $file->name, $mimeType, Asset::SOURCE_UPLOAD, $metadata);
    }

    public function createFromContents(string $contents, string $filename, string $mimeType, string $source, array $metadata = []): Asset
    {
        $type = str_starts_with($mimeType, 'image/') ? Asset::TYPE_IMAGE : Asset::TYPE_FILE;
        [$width, $height] = $this->imageDimensions($contents, $type);
        $hash = hash('sha256', $contents);
        $extension = $this->extensionFromMime($mimeType, pathinfo($filename, PATHINFO_EXTENSION));
        $storagePath = $this->buildStoragePath($source, $hash, $extension);

        $this->storage()->write($storagePath, $contents);

        $class = $this->assetClass();
        /** @var Asset $asset */
        $asset = new $class();
        $asset->type = $type;
        $asset->source = $source;
        $asset->status = Asset::STATUS_READY;
        $asset->mime_type = $mimeType;
        $asset->filename = $filename ?: basename($storagePath);
        $asset->storage_path = $storagePath;
        $asset->public_token = Asset::generateToken();
        $asset->size = strlen($contents);
        $asset->width = $width;
        $asset->height = $height;
        $asset->hash = $hash;
        $asset->setMetadataArray($metadata);

        if (!$asset->save()) {
            $this->storage()->delete($storagePath);
            throw new InvalidArgumentException(current($asset->firstErrors) ?: 'No se pudo guardar el asset.');
        }

        return $asset;
    }

    public function read(Asset $asset): string
    {
        return $this->storage()->read($asset->storage_path);
    }

    public function dataUrl(Asset $asset): string
    {
        return 'data:' . $asset->mime_type . ';base64,' . base64_encode($this->read($asset));
    }

    private function storage(): AssetStorage
    {
        return $this->module()->getAssetStorage();
    }

    private function assetClass(): string
    {
        return $this->module()->assetClass;
    }

    private function module(): \eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }

    private function detectMimeType(string $path, ?string $fallback = null): string
    {
        $mime = FileHelper::getMimeType($path);
        return $mime ?: ($fallback ?: 'application/octet-stream');
    }

    private function imageDimensions(string $contents, string $type): array
    {
        if ($type !== Asset::TYPE_IMAGE) {
            return [null, null];
        }
        $size = @getimagesizefromstring($contents);
        return $size === false ? [null, null] : [(int)$size[0], (int)$size[1]];
    }

    private function extensionFromMime(string $mimeType, ?string $fallback): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        return $map[$mimeType] ?? preg_replace('/[^a-z0-9]/i', '', strtolower($fallback ?: 'bin'));
    }

    private function buildStoragePath(string $source, string $hash, string $extension): string
    {
        return implode('/', ['ai-agent', $source, date('Y'), date('m'), substr($hash, 0, 2), $hash . '.' . $extension]);
    }
}
