<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem;
use Lastdino\AssetGuard\Services\OperatingStatusService;
use Livewire\Attributes\On;
use Livewire\Component;

class MonthlyInspectionTable extends Component
{
    public int $assetId;

    public string $yearMonth;

    public function mount(int $assetId, ?string $yearMonth = null): void
    {
        $this->assetId = $assetId;
        $this->yearMonth = $yearMonth ?? Carbon::now()->format('Y-m');
    }

    #[On('executed')]
    #[On('saved-draft')]
    public function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }

    public function getAssetProperty(): AssetGuardAsset
    {
        return AssetGuardAsset::with(['assetType'])->findOrFail($this->assetId);
    }

    public function getChecklistsWithItemsProperty(): Collection
    {
        $asset = $this->asset;

        $assetChecklists = $asset->inspectionChecklists()->with('items')->get();
        $typeChecklists = $asset->assetType?->checklists()->with('items')->get() ?? collect();

        return $assetChecklists->merge($typeChecklists)
            ->unique('id')
            ->values()
            ->map(function ($checklist) {
                return (object) [
                    'id' => $checklist->id,
                    'name' => $checklist->name,
                    'items' => $checklist->items->sortBy('sort_order'),
                ];
            });
    }

    public function getDaysInMonthProperty(): int
    {
        return Carbon::parse($this->yearMonth)->daysInMonth;
    }

    public function getResultsProperty(): array
    {
        $startOfMonth = Carbon::parse($this->yearMonth)->startOfMonth();
        $endOfMonth = Carbon::parse($this->yearMonth)->endOfMonth();

        $inspections = AssetGuardInspection::query()
            ->where('asset_id', $this->assetId)
            ->whereBetween('performed_at', [$startOfMonth, $endOfMonth])
            ->with(['results', 'performer'])
            ->get();

        $matrix = [];
        foreach ($inspections as $inspection) {
            $day = $inspection->performed_at->day;
            $performerName = $inspection->performer?->name ?? 'Unknown';
            foreach ($inspection->results as $result) {
                $matrix[$result->checklist_item_id][$day] = [
                    'value' => $result->value,
                    'result' => strtolower($result->result),
                    'id' => $result->id,
                    'performer' => $performerName,
                ];
            }
        }

        return $matrix;
    }

    public function getSchedulesProperty(): array
    {
        $startOfMonth = Carbon::parse($this->yearMonth)->startOfMonth();
        $endOfMonth = Carbon::parse($this->yearMonth)->endOfMonth();

        $plans = \Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan::query()
            ->where('asset_id', $this->assetId)
            ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
            ->get();

        $matrix = [];
        foreach ($plans as $plan) {
            $day = $plan->scheduled_at->day;
            $matrix[$plan->checklist_id][$day] = true;
        }

        return $matrix;
    }

    public function getOperatingStatusesProperty(): array
    {
        $startOfMonth = Carbon::parse($this->yearMonth)->startOfMonth();
        $endOfMonth = Carbon::parse($this->yearMonth)->endOfMonth();
        $service = app(OperatingStatusService::class);
        $asset = $this->asset;

        $statuses = [];
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $date = $startOfMonth->copy()->day($day);
            $statuses[$day] = $service->getStatusForDate($asset, $date);
        }

        return $statuses;
    }

    public function toggleOperatingStatus(int $day): void
    {
        $date = Carbon::parse($this->yearMonth)->day($day);
        $asset = $this->asset;

        // その日の終わりの時刻でステータスを切り替える（簡略化のため）
        app(OperatingStatusService::class)->toggleStatus($asset, $date->endOfDay());

        $this->dispatch('refreshTable');
    }

    public function openEntryModal(int $itemId, int $day): void
    {
        $date = Carbon::parse($this->yearMonth)->day($day)->toDateString();

        // Find which checklist this item belongs to
        $item = AssetGuardInspectionChecklistItem::findOrFail($itemId);

        $this->dispatch('open-inspection', [
            'mode' => 'monthly',
            'assetId' => $this->assetId,
            'checklistId' => $item->checklist_id,
            'date' => $date,
        ]);
    }

    public function previousMonth(): void
    {
        $this->yearMonth = Carbon::parse($this->yearMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->yearMonth = Carbon::parse($this->yearMonth)->addMonth()->format('Y-m');
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.monthly-inspection-table');
    }
}
