<?php

namespace eseperio\aiagent\services;

use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class AudioTranscriptionService extends Component
{
    public int $maxUploadSize = 26214400;
    public array $allowedMimeTypes = [
        'audio/mp3',
        'audio/mpeg',
        'audio/mp4',
        'audio/m4a',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
        'audio/x-m4a',
        'audio/x-wav',
    ];

    public function transcribeUpload(UploadedFile $file, ?string $model = null, ?string $prompt = null): array
    {
        if ($file->size > $this->maxUploadSize) {
            throw new InvalidArgumentException('El audio supera el tamaño máximo permitido.');
        }

        if (!is_file($file->tempName) || !is_readable($file->tempName)) {
            throw new InvalidArgumentException('No se pudo leer el audio subido.');
        }

        $mimeType = $this->detectMimeType($file->tempName, $file->type);
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Tipo de audio no permitido: ' . $mimeType);
        }

        $fields = [
            'model' => $this->module()->resolveTranscriptionModel($model),
            'prompt' => is_string($prompt) ? trim($prompt) : null,
        ];

        $response = $this->clientCall('createTranscription', [$fields, $file->tempName, $file->name]);
        $text = trim((string)($response['text'] ?? $response['transcript'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('La transcripción no devolvió texto.');
        }

        return [
            'text' => $text,
            'model' => (string)$fields['model'],
            'mime_type' => $mimeType,
            'language' => isset($response['language']) ? (string)$response['language'] : null,
        ];
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

    private function detectMimeType(string $path, ?string $fallback = null): string
    {
        $fallback = is_string($fallback) ? trim(strtolower($fallback)) : null;
        $detected = FileHelper::getMimeType($path);
        if (
            $fallback !== null
            && str_starts_with($fallback, 'audio/')
            && ($detected === null || $detected === 'application/octet-stream' || $detected === 'text/plain')
        ) {
            return $fallback;
        }

        return $detected ?: ($fallback ?: 'application/octet-stream');
    }

    private function module(): \eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }
}
