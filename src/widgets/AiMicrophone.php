<?php

namespace eseperio\aiagent\widgets;

use eseperio\aiagent\assets\AiChatAsset;
use yii\base\Widget;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

class AiMicrophone extends Widget
{
    public ?string $prompt = null;
    public ?string $model = null;
    public ?string $transcriptionModel = null;
    public ?string $eventName = null;
    public ?string $label = null;
    public ?string $title = null;
    public array $htmlOptions = [];
    public ?bool $dictationEnabled = null;

    public function run(): string
    {
        $module = $this->getModule();
        if (!$module || !($this->dictationEnabled ?? $module->dictationEnabled)) {
            return '';
        }

        try {
            $assetManager = \Yii::$app->has('assetManager') ? \Yii::$app->assetManager : null;
            if ($assetManager && is_string($assetManager->basePath) && $assetManager->basePath !== '' && !is_dir($assetManager->basePath)) {
                FileHelper::createDirectory($assetManager->basePath);
            }
            AiChatAsset::register($this->getView());
        } catch (\yii\base\InvalidConfigException) {
        }

        $options = $this->htmlOptions;
        $options['class'] = trim(($options['class'] ?? '') . ' ai-agent-microphone ai-assistant-microphone-mount');
        $options['data'] = array_merge($options['data'] ?? [], [
            'props' => Json::htmlEncode($this->buildProps($module)),
        ]);

        return Html::tag('div', '', $options);
    }

    protected function buildProps(\eseperio\aiagent\Module $module): array
    {
        return [
            'prompt' => trim((string)$this->prompt),
            'model' => $module->resolveModel($this->model),
            'transcriptionModel' => $module->resolveTranscriptionModel($this->transcriptionModel),
            'eventName' => trim((string)($this->eventName ?? 'ai-agent:microphone-result')),
            'label' => trim((string)($this->label ?? '🎤')),
            'title' => trim((string)($this->title ?? 'Grabar audio')),
            'apiUrls' => [
                'processAudio' => $this->routeUrl('process-audio'),
            ],
        ];
    }

    protected function getModule(): ?\eseperio\aiagent\Module
    {
        return \eseperio\aiagent\Module::resolveActive();
    }

    private function routeUrl(string $action): string
    {
        $route = '/' . ($this->getModule()?->id ?? 'aiAgent') . '/chat/' . $action;
        try {
            return Url::to([$route]);
        } catch (\Throwable) {
            return '/index.php?r=' . ltrim($route, '/');
        }
    }
}
