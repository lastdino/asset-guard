<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Lastdino\AssetGuard\Models\AssetGuardAsset as Asset;

class PreUseInspectionGate
{
    public function __construct(public int $assetId) { }

    public function isInspectionRequired(): bool
    {
        $assetTypeId = Asset::query()->whereKey($this->assetId)->value('asset_type_id');

        $plans = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $this->assetId)
            ->where(function ($q) {
                $q->where('trigger_type', 'per_use')
                  ->orWhereNull('trigger_type'); // fallback for older records
            })
            ->where('status', 'Scheduled')
            ->whereHas('checklist', function ($q) use ($assetTypeId) {
                $q->where('require_before_activation', true)
                  ->where('active', true)
                  ->where(function ($q) use ($assetTypeId) {
                      $q->where(function ($q) use ($assetTypeId) {
                          $q->where('applies_to', 'asset_type')
                            ->when($assetTypeId, fn ($q) => $q->where('asset_type_id', $assetTypeId));
                      })->orWhere(function ($q) {
                          $q->where('applies_to', 'asset');
                      });
                  });
            })
            ->get();

        if ($plans->isEmpty()) {
            return false;
        }

        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        foreach ($plans as $plan) {
            $lastCompleted = AssetGuardInspection::query()
                ->where('asset_id', $this->assetId)
                ->where('checklist_id', $plan->checklist_id)
                ->where('status', 'Completed')
                ->latest('performed_at')
                ->first();

            if (! $lastCompleted) {
                return true;
            }

            $performed = $lastCompleted->performed_at?->copy()->timezone($tz);
            if ($performed === null || ! $performed->isSameDay($now)) {
                return true;
            }
        }

        return false; // all completed today
    }
}
