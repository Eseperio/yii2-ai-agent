<?php

namespace eseperio\aiagent\contracts;

use eseperio\aiagent\models\Asset;

interface AssetTargetHandlerInterface
{
    public function supports(string|int $targetType): bool;

    public function attach(Asset $asset, string|int $targetType, string|int $targetId, array $options = []): array;
}
