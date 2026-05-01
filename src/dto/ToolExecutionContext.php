<?php

namespace eseperio\aiagent\dto;

class ToolExecutionContext
{
    public function __construct(
        public mixed $conversation = null,
        public mixed $message = null,
        public ?string $toolCallId = null,
        public ?string $responseId = null,
        public array $contexts = [],
        public mixed $user = null,
        public mixed $request = null,
        public array $toolSnapshot = [],
        public array $metadata = []
    ) {
    }
}
