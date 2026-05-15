<?php

namespace eseperio\aiagent\actions\chat;

use yii\base\InvalidArgumentException;
use yii\web\UploadedFile;

class ProcessAudioAction extends BaseChatAction
{
    public function run()
    {
        if (!$this->module()?->dictationEnabled) {
            return $this->deny('Dictation disabled', 404);
        }

        if (!$this->can('canSendMessage', $this->permissionContext('processAudio'))) {
            return $this->deny();
        }

        $audio = UploadedFile::getInstanceByName('audio');
        if (!$audio) {
            return $this->json(['success' => false, 'error' => 'No audio uploaded'], 400);
        }

        $transcriptionModel = $this->request()->post('transcription_model');
        $prompt = trim((string)$this->request()->post('prompt', ''));
        $processingModel = trim((string)$this->request()->post('model', ''));

        if ($processingModel !== '' && !$this->can('canUseModel', $this->permissionContext('processAudio', ['model' => $processingModel]))) {
            return $this->deny('Model not allowed');
        }

        try {
            $transcription = $this->module()?->getAudioTranscriptionService()->transcribeUpload(
                $audio,
                is_string($transcriptionModel) ? $transcriptionModel : null,
                $prompt !== '' ? $prompt : null
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'error' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            \Yii::error($exception, __METHOD__);
            return $this->json(['success' => false, 'error' => 'No se pudo transcribir el audio.'], 502);
        }

        $output = null;
        $responseId = null;
        if ($prompt !== '') {
            try {
                ['output' => $output, 'response_id' => $responseId] = $this->processPrompt(
                    $transcription['text'],
                    $prompt,
                    $processingModel !== '' ? $processingModel : null
                );
            } catch (InvalidArgumentException $exception) {
                return $this->json(['success' => false, 'error' => $exception->getMessage()], 400);
            } catch (\Throwable $exception) {
                \Yii::error($exception, __METHOD__);
                return $this->json(['success' => false, 'error' => 'No se pudo procesar la transcripción.'], 502);
            }
        }

        return $this->json([
            'success' => true,
            'transcription' => $transcription['text'],
            'transcription_model' => $transcription['model'],
            'language' => $transcription['language'],
            'output' => $output,
            'response_id' => $responseId,
        ]);
    }

    private function processPrompt(string $transcription, string $prompt, ?string $requestedModel = null): array
    {
        $module = $this->module();
        if (!$module) {
            throw new InvalidArgumentException('Module unavailable');
        }

        $model = $module->resolveModel($requestedModel);
        $client = $module->getClientFactory()->create($module->clientConfig);
        $response = $client->createResponse([
            'model' => $model,
            'instructions' => $prompt,
            'input' => [
                ['role' => 'user', 'content' => $transcription],
            ],
        ]);
        if (isset($response['error'])) {
            $message = is_array($response['error'])
                ? (string)($response['error']['message'] ?? 'The AI provider returned an error.')
                : (string)$response['error'];
            throw new InvalidArgumentException($message);
        }

        $parsed = $module->getResponseParser()->parse($response);
        $payload = $module->getResponseParser()->parseText((string)($parsed['text'] ?? ''));
        $output = trim((string)($payload['response'] ?? $payload['text'] ?? $parsed['text'] ?? ''));
        if ($output === '') {
            throw new InvalidArgumentException('El procesado no devolvió texto.');
        }

        return [
            'output' => $output,
            'response_id' => $parsed['id'] ?? null,
        ];
    }
}
