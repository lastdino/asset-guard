<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lastdino\AssetGuard\Events\AssetTypeChanged;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist as Checklist;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan as Plan;

class SyncMaintenancePlansOnAssetTypeChanged implements ShouldQueue
{

    public function handle(AssetTypeChanged $event): void
    {
        $this->cancelOldTypePlans($event->assetId, $event->oldTypeId);
        $this->createNewTypePlans($event->assetId, $event->newTypeId);
    }

    protected function cancelOldTypePlans(int $assetId, ?int $oldTypeId): void
    {
        if (! $oldTypeId) {
            return;
        }

        $plans = Plan::query()
            ->where('asset_id', $assetId)
            ->whereHas('checklist', function ($q) use ($oldTypeId) {
                $q->where('applies_to', 'asset_type')
                  ->where('asset_type_id', $oldTypeId);
            })
            ->get();

        foreach ($plans as $plan) {
            $plan->update(['status' => 'Cancelled']);
        }
    }

    protected function createNewTypePlans(int $assetId, ?int $newTypeId): void
    {
        if (! $newTypeId) {
            return;
        }

        $checklists = Checklist::query()
            ->where('active', true)
            ->where('applies_to', 'asset_type')
            ->where('asset_type_id', $newTypeId)
            ->get();

        foreach ($checklists as $checklist) {
            $exists = Plan::query()
                ->where('asset_id', $assetId)
                ->where('checklist_id', $checklist->id)
                ->whereNotIn('status', ['Cancelled'])
                ->exists();

            if ($exists) {
                continue;
            }

            Plan::query()->create([
                'asset_id' => $assetId,
                'checklist_id' => $checklist->id,
                'title' => $checklist->name,
                'status' => 'Scheduled',
                'timezone' => config('app.timezone'),
                'scheduled_at' => now(),
            ]);
        }
    }
}
