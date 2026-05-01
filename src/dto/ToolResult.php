<?php

namespace eseperio\aiagent\dto;

class ToolResult
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
        public array $createdContexts = [],
        public array $updatedContexts = [],
        public ?string $message = null
    ) {
    }
}
