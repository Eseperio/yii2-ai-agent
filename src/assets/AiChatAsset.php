<?php

namespace eseperio\aiagent\assets;

use yii\web\AssetBundle;

class AiChatAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $js = ['js/ai-chat.js'];
    public $css = ['css/ai-chat.css'];
}
