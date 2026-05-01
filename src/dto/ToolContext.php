<?php

namespace eseperio\aiagent\dto;

class ToolContext
{
    public function __construct(
        public mixed $conversation = null,
        public array $contexts = [],
        public mixed $user = null,
        public mixed $request = null,
        public ?string $model = null,
        public array $metadata = []
    ) {
    }
}
