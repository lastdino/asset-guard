<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Facades\DB;
use Lastdino\AssetGuard\Models\{AssetGuardAsset as Asset, AssetGuardInspectionChecklist as Checklist, AssetGuardMaintenancePlan as Plan};

class SyncPreUsePlanForAsset
{
    public function handle(Asset $asset): void
    {
        // Determine all eligible pre-use checklists for the current asset
        $eligibleChecklistIds = Checklist::query()
            ->where('active', true)
            ->where('require_before_activation', true)
            ->where(function ($q) use ($asset) {
                $q->where(function ($q) use ($asset) {
                    $q->where('applies_to', 'asset_type')
                      ->where('asset_type_id', (int) $asset->asset_type_id);
                })->orWhere(function ($q) use ($asset) {
                    $q->where('applies_to', 'asset')
                      ->where('asset_id', $asset->id);
                });
            })
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($asset, $eligibleChecklistIds): void {
            // Fetch existing scheduled plans for this asset for these checklists
            $existing = Plan::query()
                ->where('asset_id', $asset->id)
                ->where('status', 'Scheduled')
                ->get(['id', 'checklist_id']);

            $existingByChecklist = $existing->keyBy('checklist_id');

            // Archive plans that are no longer eligible (for per-use, we don't keep plans)
            if ($existing->isNotEmpty()) {
                Plan::query()
                    ->whereIn('id', $existing->pluck('id'))
                    ->when(! empty($eligibleChecklistIds), function ($q) use ($eligibleChecklistIds) {
                        $q->whereNotIn('checklist_id', $eligibleChecklistIds);
                    }, function ($q) {
                        // If no eligible checklists remain, archive all existing
                        return $q; // no-op; condition retained
                    })
                    ->update(['status' => 'Archived']);
            }

            // Do not create new plans for per-use checklists
            // This service now only archives inappropriate plans.
        });
    }
}
