<?php

namespace eseperio\aiagent\dto;

class ContextRenderContext
{
    public function __construct(
        public mixed $conversation = null,
        public mixed $user = null,
        public mixed $request = null,
        public array $metadata = []
    ) {
    }
}
