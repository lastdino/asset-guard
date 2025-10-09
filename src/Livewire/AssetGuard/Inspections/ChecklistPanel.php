<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Livewire\Component;
use Livewire\Attributes\On;
use Lastdino\AssetGuard\Models\{AssetGuardAsset, AssetGuardInspectionChecklist, AssetGuardMaintenancePlan as Plan};

class ChecklistPanel extends Component
{
    public int $assetId;

    public array $form = [
        'id' => null,
        'name' => '',
        'applies_to' => 'asset',
        'active' => true,
        'frequency_unit' => 'Monthly',
        'frequency_value' => 1,
        'require_before_activation' => false,
    ];

    public bool $showModal = false;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    protected function rules(): array
    {
        return [
            'form.id' => ['nullable','integer'],
            'form.name' => ['required','string','max:255'],
            'form.applies_to' => ['required','in:asset,type'],
            'form.active' => ['required','boolean'],
            // Include PerUse and allow frequency_value to be optional when PerUse is selected
            'form.frequency_unit' => ['required','in:OneTime,PerUse,Daily,Weekly,Monthly,Quarterly,SemiAnnual,Annual,Custom'],
            'form.frequency_value' => ['required_unless:form.frequency_unit,PerUse','integer','min:1','max:365'],
            'form.require_before_activation' => ['required','boolean'],
        ];
    }

    public function openCreate(): void
    {
        $this->form = [
            'id' => null,
            'name' => '',
            'applies_to' => 'asset',
            'active' => true,
            'frequency_unit' => 'Monthly',
            'frequency_value' => 1,
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cl = AssetGuardInspectionChecklist::query()
            ->where('asset_id', $this->assetId)
            ->findOrFail($id);

        $this->form = [
            'id' => (int) $cl->id,
            'name' => $cl->name,
            'applies_to' => strtolower((string) $cl->applies_to),
            'active' => (bool) $cl->active,
            'frequency_unit' => (string) ($cl->frequency_unit ?? 'Monthly'),
            'frequency_value' => (int) ($cl->frequency_value ?? 1),
            'require_before_activation' => (bool) ($cl->require_before_activation ?? false),
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate()['form'];

        $payload = [
            'name' => $data['name'],
            'applies_to' => $data['applies_to'],
            'asset_id' => $this->assetId,
            'active' => (bool) $data['active'],
            'frequency_unit' => $data['frequency_unit'],
            'frequency_value' => (int) $data['frequency_value'],
            'require_before_activation' => (bool) $data['require_before_activation'],
        ];

        if (!empty($data['id'])) {
            $cl = AssetGuardInspectionChecklist::query()
                ->where('asset_id', $this->assetId)
                ->findOrFail((int) $data['id']);
            $cl->update($payload);
        } else {
            $cl = AssetGuardInspectionChecklist::query()->create($payload);
        }

        // Auto-sync: ensure/remove per-use plan for this asset-level checklist
        $this->syncPlanForChecklist($cl);

        $this->dispatch('checklist-saved');
        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $cl = AssetGuardInspectionChecklist::query()
            ->where('asset_id', $this->assetId)
            ->findOrFail($id);

        // Remove related per-use Scheduled plans before deleting the checklist
        $this->removePlanForChecklist($cl);

        $cl->delete();

        $this->dispatch('checklist-deleted');
    }

    public function getChecklistsProperty()
    {
        return AssetGuardAsset::query()
            ->with('inspectionChecklists.items')
            ->find($this->assetId)?->inspectionChecklists ?? collect();
    }

    #[On('item-saved')]
    #[On('item-deleted')]
    public function refreshCounts(): void
    {
        $this->dispatch('$refresh');
    }

    protected function syncPlanForChecklist(AssetGuardInspectionChecklist $cl): void
    {
        // Only for asset-applied checklists on this asset
        if ($cl->applies_to !== 'asset' || (int) $cl->asset_id !== (int) $this->assetId) {
            return;
        }

        // Only handle pre-use style checklists that are active and required before activation
        if (! (bool) $cl->active || ! (bool) $cl->require_before_activation) {
            // Not eligible -> archive any existing Scheduled per_use plans for this checklist on this asset
            $this->removePlanForChecklist($cl);
            return;
        }

        // Ensure a Scheduled per_use plan exists
        $exists = Plan::query()
            ->where('asset_id', $this->assetId)
            ->where('checklist_id', $cl->id)
            ->where(function ($q) { $q->where('trigger_type', 'per_use')->orWhereNull('trigger_type'); })
            ->where('status', 'Scheduled')
            ->exists();

        if ($exists) {
            return;
        }

        Plan::query()->create([
            'asset_id' => $this->assetId,
            'checklist_id' => $cl->id,
            'trigger_type' => 'per_use',
            'status' => 'Scheduled',
            'timezone' => config('app.timezone'),
            'require_before_activation' => (bool) $cl->require_before_activation,
            'title' => $cl->name,
        ]);
    }

    protected function removePlanForChecklist(AssetGuardInspectionChecklist $cl): void
    {
        if ($cl->applies_to !== 'asset' || (int) $cl->asset_id !== (int) $this->assetId) {
            return;
        }

        Plan::query()
            ->where('asset_id', $this->assetId)
            ->where('checklist_id', $cl->id)
            ->where(function ($q) { $q->where('trigger_type', 'per_use')->orWhereNull('trigger_type'); })
            ->where('status', 'Scheduled')
            ->update(['status' => 'Archived']);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.checklist-panel');
    }
}
