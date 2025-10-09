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

    public function mount(int $assetTypeId): void
    {
        $this->assetTypeId = $assetTypeId;
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

        // Immediate sync: create or remove per_use Scheduled plans for all assets of this type
        if ((bool) $checklist->active && (bool) $checklist->require_before_activation) {
            $this->ensurePlansForChecklist($checklist);
        } else {
            $this->removePlansForChecklist($checklist);
        }

        $this->dispatch('saved');
        $this->openModal = false;
    }

    public function delete(int $id): void
    {
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

        Asset::query()
            ->where('asset_type_id', $cl->asset_type_id)
            ->where('status', '!=', 'Retired')
            ->orderBy('id')
            ->chunkById(500, function ($assets) use ($cl) {
                foreach ($assets as $asset) {
                    $exists = Plan::query()
                        ->where('asset_id', $asset->id)
                        ->where('checklist_id', $cl->id)
                        ->where(function ($q) { $q->where('trigger_type', 'per_use')->orWhereNull('trigger_type'); })
                        ->where('status', 'Scheduled')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    Plan::query()->create([
                        'asset_id' => $asset->id,
                        'checklist_id' => $cl->id,
                        'trigger_type' => 'per_use',
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
                        ->where(function ($q) { $q->where('trigger_type', 'per_use')->orWhereNull('trigger_type'); })
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
