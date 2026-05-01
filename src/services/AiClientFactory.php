<?php

namespace eseperio\aiagent\services;

use yii\base\Component;

class AiClientFactory extends Component
{
    public function create(array $config = []): AiClientInterface
    {
        return new OpenAiResponsesClient($config);
    }
}
