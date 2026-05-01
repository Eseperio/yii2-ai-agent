<?php

namespace eseperio\aiagent\dto;

class PermissionContext
{
    public function __construct(
        public string $action,
        public mixed $user = null,
        public mixed $request = null,
        public mixed $conversation = null,
        public mixed $message = null,
        public array $contexts = [],
        public ?string $toolName = null,
        public array $arguments = [],
        public ?string $model = null,
        public array $metadata = []
    ) {
    }
}
