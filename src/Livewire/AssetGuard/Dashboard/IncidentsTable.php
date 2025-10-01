<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lastdino\AssetGuard\Models\AssetGuardIncident;
use Livewire\Component;

class IncidentsTable extends Component
{
    public int $perPage = 10;

    public function getRowsProperty(): LengthAwarePaginator
    {
        return AssetGuardIncident::query()
            ->with(['asset'])
            ->whereIn('status', ['Waiting', 'InProgress'])
            ->latest('created_at')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.dashboard.incidents-table', [
            'rows' => $this->rows,
        ]);
    }
}
