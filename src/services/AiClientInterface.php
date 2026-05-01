<?php

namespace eseperio\aiagent\services;

interface AiClientInterface
{
    public function createResponse(array $payload): array;
}
