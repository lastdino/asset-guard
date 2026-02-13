<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Events;

class AssetTypeChanged
{
    public function __construct(
        public int $assetId,
        public ?int $oldTypeId,
        public ?int $newTypeId,
    ) {}
}
