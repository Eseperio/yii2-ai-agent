<?php

namespace eseperio\aiagent\dto;

class ToolPolicyDecision
{
    public function __construct(
        public bool $allowed,
        public bool $requiresApproval,
        public bool $allowAutonomous,
        public string $effect,
        public string $riskLevel,
        public ?string $reason = null
    ) {
    }
}
