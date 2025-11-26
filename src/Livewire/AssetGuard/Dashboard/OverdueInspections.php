<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan as Plan;
use Livewire\Component;

class OverdueInspections extends Component
{
    public int $perPage = 10;

    public function getRowsProperty(): LengthAwarePaginator
    {
        return Plan::query()
            ->with(['asset', 'checklist', 'assignee'])
            ->where('status', 'Scheduled')
            ->where('scheduled_at', '<', now()->addDays(7))
            ->orderBy('scheduled_at')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.dashboard.overdue-inspections', [
            'rows' => $this->rows,
        ]);
    }
}
