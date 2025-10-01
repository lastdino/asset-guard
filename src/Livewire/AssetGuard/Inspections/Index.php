<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionItemResult;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Livewire\Component;

class Index extends Component
{
    public int $assetId;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function getDueOccurrencesProperty()
    {
        return Occurrence::query()
            ->where('asset_id', $this->assetId)
            ->with(['asset', 'plan.checklist'])
            ->whereDate('planned_at', '<=', Carbon::now())
            ->whereIn('status', ['Scheduled','Overdue'])
            ->orderBy('planned_at')
            ->limit(100)
            ->get();
    }

    public function execute(int $occurrenceId): void
    {
        $occ = Occurrence::query()->with(['plan.checklist.items', 'asset'])->findOrFail($occurrenceId);

        $inspection = AssetGuardInspection::query()->create([
            'asset_id' => $occ->asset_id,
            'performed_by_user_id' => auth()->id(),
            'performed_at' => Carbon::now(),
            'checklist_id' => $occ->plan?->checklist?->id,
            'status' => 'Completed',
        ]);

        $items = $occ->plan?->checklist?->items ?: collect();
        foreach ($items as $item) {
            AssetGuardInspectionItemResult::query()->create([
                'inspection_id' => $inspection->id,
                'checklist_item_id' => $item->id,
                'result' => 'Pass',
                'value' => null,
                'note' => null,
                'is_draft' => false,
            ]);
        }

        $occ->update([
            'status' => 'Completed',
            'completed_at' => Carbon::now(),
        ]);

        $this->dispatch('executed');
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.index');
    }
}
