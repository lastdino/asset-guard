<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionItemResult;
// use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Livewire\Component;

class Index extends Component
{
    public int $assetId;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function getDuePlansProperty(){
        return AssetGuardMaintenancePlan::query()
            ->where('asset_id', $this->assetId)
            ->with(['asset', 'checklist'])
            ->whereDate('scheduled_at', '<=', Carbon::now())
            ->whereIn('status', ['Scheduled'])
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get();
    }


    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.index');
    }
}
