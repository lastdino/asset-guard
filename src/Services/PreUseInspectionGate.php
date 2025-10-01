<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;

class PreUseInspectionGate
{
    public function __construct(public int $assetId) { }

    public function isInspectionRequired(): bool
    {
        $plan = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $this->assetId)
            ->where(function ($q) {
                $q->where('trigger_type', 'per_use')
                  ->orWhereNull('trigger_type'); // fallback for older records, rely on checklist unit
            })
            ->where('require_before_activation', true)
            ->where('status', 'Scheduled')
            ->first();

        if (! $plan) {
            return false;
        }

        // Determine timezone
        $tz = $plan->timezone ?: config('app.timezone');
        $now = Carbon::now($tz);

        // Find last completed inspection for this plan's checklist on this asset
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
        if ($performed === null) {
            return true;
        }

        return ! $performed->isSameDay($now);
    }
}
