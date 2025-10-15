<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\AssetTypes;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View as IlluminateView;
use Lastdino\AssetGuard\Models\{AssetGuardInspectionChecklist as Checklist, AssetGuardAsset as Asset, AssetGuardMaintenancePlan as Plan};
use Livewire\Component;
use Livewire\WithPagination;

class ChecklistManager extends Component
{
    use WithPagination;

    public int $assetTypeId;

    public bool $readonly = false;

    public string $search = '';

    public bool $openModal = false; // form modal

    public bool $openManage = false; // manage flyout modal

    public ?int $editingId = null;

    /**
     * @var array{name:string,active:bool,frequency_unit:?string,frequency_value:?int,require_before_activation:bool}
     */
    public array $form = [
        'name' => '',
        'active' => true,
        'frequency_unit' => null,
        'frequency_value' => null,
        'require_before_activation' => false,
    ];

    public function mount(int $assetTypeId, bool $readonly = false): void
    {
        $this->assetTypeId = $assetTypeId;
        $this->readonly = $readonly;
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'checklistsPage');
    }

    public function getChecklistsProperty(): LengthAwarePaginator
    {
        return Checklist::query()
            ->forAssetType($this->assetTypeId)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(5, pageName: 'checklistsPage');
    }

    public function openCreate(): void
    {
        if ($this->readonly) {
            return;
        }
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'active' => true,
            'frequency_unit' => null,
            'frequency_value' => null,
            'require_before_activation' => false,
        ];

        $this->openModal = true;
    }

    public function openEdit(int $id): void
    {
        if ($this->readonly) {
            return;
        }
        $c = Checklist::query()->forAssetType($this->assetTypeId)->findOrFail($id);
        $this->editingId = $c->id;
        $this->form = [
            'name' => $c->name,
            'active' => (bool) $c->active,
            'frequency_unit' => $c->frequency_unit,
            'frequency_value' => $c->frequency_value,
            'require_before_activation' => (bool) $c->require_before_activation,
        ];

        $this->openModal = true;
    }

    public function save(): void
    {
        if ($this->readonly) {
            return;
        }
        $validated = $this->validate([
            'form.name' => ['required','string','max:255'],
            'form.active' => ['required','boolean'],
            'form.frequency_unit' => ['nullable','string','in:OneTime,PerUse,Daily,Weekly,Monthly,Quarterly,SemiAnnual,Annual,Custom,day,week,month,year'],
            'form.frequency_value' => ['nullable','integer','min:1','max:65535'],
            'form.require_before_activation' => ['required','boolean'],
        ], [], [
            'form.name' => __('asset-guard::checklists.name'),
            'form.active' => __('asset-guard::checklists.active'),
            'form.frequency_unit' => __('asset-guard::checklists.frequency_unit'),
            'form.frequency_value' => __('asset-guard::checklists.frequency_value'),
            'form.require_before_activation' => __('asset-guard::checklists.require_before_activation'),
        ]);

        $data = array_merge($validated['form'], [
            'applies_to' => 'asset_type',
            'asset_type_id' => $this->assetTypeId,
        ]);

        if ($this->editingId) {
            $checklist = Checklist::query()->forAssetType($this->assetTypeId)->findOrFail($this->editingId);
            $checklist->update($data);
        } else {
            $checklist = Checklist::query()->create($data);
        }

        // Immediate sync: if active, ensure a Scheduled plan per asset based on frequency; otherwise remove
        if ((bool) $checklist->active) {
            $this->ensurePlansForChecklist($checklist);
        } else {
            $this->removePlansForChecklist($checklist);
        }

        $this->dispatch('saved');
        $this->openModal = false;
    }

    public function delete(int $id): void
    {
        if ($this->readonly) {
            return;
        }
        $checklist = Checklist::query()->forAssetType($this->assetTypeId)->findOrFail($id);

        // Remove related per_use Scheduled plans before deleting the checklist
        $this->removePlansForChecklist($checklist);

        $checklist->delete();
        $this->dispatch('deleted');
    }

    protected function ensurePlansForChecklist(Checklist $cl): void
    {
        // Only for type-applied checklists
        if ($cl->applies_to !== 'asset_type' || empty($cl->asset_type_id)) {
            return;
        }


        $newTrigger = ($cl->require_before_activation === true) ? 'per_use' : 'time';

        Asset::query()
            ->where('asset_type_id', $cl->asset_type_id)
            ->where('status', '!=', 'Retired')
            ->orderBy('id')
            ->chunkById(500, function ($assets) use ($cl, $newTrigger) {
                foreach ($assets as $asset) {
                    $existing = Plan::query()
                        ->where('asset_id', $asset->id)
                        ->where('checklist_id', $cl->id)
                        ->where('status', 'Scheduled')
                        ->get();

                    $current = $existing->firstWhere('trigger_type', $newTrigger)
                        ?? $existing->firstWhere('trigger_type', null);

                    // Archive any Scheduled plans that don't match the new trigger
                    $existing
                        ->filter(fn ($p) => (string) $p->trigger_type !== (string) $newTrigger)
                        ->each(fn ($p) => $p->update(['status' => 'Archived']));

                    if ($current) {
                        $current->update([
                            'title' => $cl->name,
                            'trigger_type' => $newTrigger,
                            'require_before_activation' => (bool) $cl->require_before_activation,
                            'timezone' => config('app.timezone'),
                        ]);
                        continue;
                    }

                    Plan::query()->create([
                        'asset_id' => $asset->id,
                        'checklist_id' => $cl->id,
                        'trigger_type' => $newTrigger,
                        'status' => 'Scheduled',
                        'timezone' => config('app.timezone'),
                        'require_before_activation' => (bool) $cl->require_before_activation,
                        'title' => $cl->name,
                    ]);
                }
            });
    }

    protected function removePlansForChecklist(Checklist $cl): void
    {
        if ($cl->applies_to !== 'asset_type' || empty($cl->asset_type_id)) {
            return;
        }

        Asset::query()
            ->where('asset_type_id', $cl->asset_type_id)
            ->orderBy('id')
            ->chunkById(500, function ($assets) use ($cl) {
                foreach ($assets as $asset) {
                    Plan::query()
                        ->where('asset_id', $asset->id)
                        ->where('checklist_id', $cl->id)
                        ->where('status', 'Scheduled')
                        ->each(function (Plan $plan) {
                            // delete pending occurrences if exist
                            $plan->occurrences()
                                ->whereIn('status', ['Scheduled','Overdue'])
                                ->delete();

                            $plan->delete();
                        });
                }
            });
    }

    public function render(): IlluminateView
    {
        return view('asset-guard::livewire.asset-guard.asset-types.checklist-manager');
    }
}
