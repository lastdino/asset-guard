<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Collection;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Livewire\Component;

class ChecklistHistoryPanel extends Component
{
    public int $assetId;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function getChecklistsProperty(): Collection
    {
        $asset = AssetGuardAsset::query()
            ->with([
                'inspectionChecklists.items',
                'assetType.checklists.items',
            ])
            ->find($this->assetId);

        if (! $asset) {
            return collect();
        }

        return $asset->inspectionChecklists
            ->merge($asset->assetType?->checklists ?? collect())
            ->unique('id')
            ->values();
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.checklist-history-panel');
    }
}
