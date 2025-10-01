<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionItemResult};

class ChecklistHistoryList extends Component
{
    use WithPagination;

    public int $checklistId;
    public ?string $from = null;
    public ?string $to = null;
    public int $perPage = 10;

    public function mount(int $checklistId, ?string $from = null, ?string $to = null): void
    {
        $this->checklistId = $checklistId;
        $this->from = $from;
        $this->to = $to;
    }

    protected function getPageName(): string
    {
        return 'inspections_' . $this->checklistId;
    }

    protected function query(): Builder
    {
        return AssetGuardInspection::query()
            ->whereHas('results.item', function (Builder $q) {
                $q->where('checklist_id', $this->checklistId);
            })
            ->when($this->from, fn(Builder $q) => $q->whereDate('performed_at', '>=', $this->from))
            ->when($this->to, fn(Builder $q) => $q->whereDate('performed_at', '<=', $this->to))
            ->latest('performed_at')
            ->with(['performer']);
    }

    public function getInspectionsProperty(): LengthAwarePaginator
    {
        return $this->query()->paginate(
            perPage: $this->perPage,
            pageName: $this->getPageName(),
        );
    }

    public function updatedFrom(): void { $this->resetPage(pageName: $this->getPageName()); }
    public function updatedTo(): void { $this->resetPage(pageName: $this->getPageName()); }
    public function updatedPerPage(): void { $this->resetPage(pageName: $this->getPageName()); }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.checklist-history-list', [
            'inspections' => $this->inspections,
        ]);
    }
}
