<?php

namespace eseperio\aiagent\dto;

class ManualDefinition
{
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public string|array|\Closure $content,
        public array $contextTypes = [],
        public mixed $available = null,
        public array $metadata = []
    ) {
    }
}
