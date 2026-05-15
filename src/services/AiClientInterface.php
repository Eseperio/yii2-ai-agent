<?php

namespace eseperio\aiagent\services;

interface AiClientInterface
{
    public function createResponse(array $payload): array;

    public function createTranscription(array $fields, string $filePath, ?string $fileName = null): array;
}
