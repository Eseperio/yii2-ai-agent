<?php

namespace eseperio\aiagent\contracts;

use eseperio\aiagent\dto\ManualContext;

interface ManualProviderInterface
{
    public function getManuals(ManualContext $context): array;
}
