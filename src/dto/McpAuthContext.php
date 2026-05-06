<?php

namespace eseperio\aiagent\dto;

class McpAuthContext
{
    public function __construct(
        public array $scopes = [],
        public mixed $user = null,
        public ?string $subject = null,
        public ?string $clientId = null,
        public ?string $tokenId = null,
        public ?string $rawToken = null,
        public array $claims = []
    ) {
    }
}
