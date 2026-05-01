<?php

namespace eseperio\aiagent\dto;

class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public mixed $handler = null,
        public bool $requiresApproval = false,
        public ?string $providerId = null,
        public array $contextTypes = [],
        public mixed $available = null,
        public array $metadata = []
    ) {
    }
}
