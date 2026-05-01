<?php

namespace eseperio\aiagent\contracts;

use eseperio\aiagent\dto\InstructionContext;

interface InstructionProviderInterface
{
    public function buildInstructions(InstructionContext $context): string;
}
