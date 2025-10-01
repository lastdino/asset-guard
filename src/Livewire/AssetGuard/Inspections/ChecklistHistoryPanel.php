<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Livewire\Component;
use Lastdino\AssetGuard\Models\AssetGuardAsset;

class ChecklistHistoryPanel extends Component
{
    public int $assetId;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function getChecklistsProperty()
    {
        return AssetGuardAsset::query()
            ->with('inspectionChecklists')
            ->find($this->assetId)?->inspectionChecklists ?? collect();
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.checklist-history-panel');
    }
}
