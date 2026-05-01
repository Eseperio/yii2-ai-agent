<?php

namespace eseperio\aiagent\tests;

use eseperio\aiagent\services\AiClientFactory;
use eseperio\aiagent\services\AiClientInterface;

class FakeAiClientFactory extends AiClientFactory
{
    public function create(array $config = []): AiClientInterface
    {
        return new FakeAiClient();
    }
}
