<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Livewire\Component;

class OverdueInspections extends Component
{
    public int $perPage = 10;

    public function getRowsProperty(): LengthAwarePaginator
    {
        return Occurrence::query()
            ->with(['asset', 'plan', 'plan.checklist', 'plan.assignee'])
            ->whereNull('completed_at')
            ->where('planned_at', '<', now()->addDays(7))
            ->orderBy('planned_at')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.dashboard.overdue-inspections', [
            'rows' => $this->rows,
        ]);
    }
}
