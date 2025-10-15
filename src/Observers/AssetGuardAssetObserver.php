<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Observers;

use Illuminate\Support\Facades\Event;
use Lastdino\AssetGuard\Events\AssetTypeChanged;
use Lastdino\AssetGuard\Models\AssetGuardAsset;

class AssetGuardAssetObserver
{
    /**
     * Whether the observer should run after the transaction is committed.
     * Laravel will respect this flag on queued events/listeners.
     */
    public bool $afterCommit = true;

    public function updated(AssetGuardAsset $asset): void
    {
        if (! $asset->wasChanged('asset_type_id')) {
            return;
        }

        $old = $asset->getOriginal('asset_type_id');
        $oldTypeId = $old !== null ? (int) $old : null;
        $newTypeId = $asset->asset_type_id !== null ? (int) $asset->asset_type_id : null;

        Event::dispatch(new AssetTypeChanged(
            assetId: (int) $asset->getKey(),
            oldTypeId: $oldTypeId,
            newTypeId: $newTypeId,
        ));
    }
}
