<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Livewire\Component;
use Lastdino\AssetGuard\Models\{AssetGuardAsset, AssetGuardMaintenanceOccurrence, AssetGuardInspectionChecklist};

class Quick extends Component
{
    public string $code = '';
    public ?int $foundAssetId = null;
    public ?string $message = null;

    // Page-entry inspector setup modal
    public bool $inspectorSetupOpen = false;
    public bool $inspectorSetupConfirmed = false;

    // Wizard state (after search)
    public bool $showStartModal = false;
    public ?int $inspectorId = null;
    /** @var array<int,int> */
    public array $coInspectorIds = [];
    public ?int $selectedChecklistId = null;

    // Resolved context
    public ?int $pendingOccurrenceId = null;
    /** @var array<int, array{id:int,name:string,pre_use:bool}> */
    public array $availableChecklists = [];

    public function mount(): void
    {
        $this->inspectorId = auth()->id();
        // Open inspector setup on page load
        $this->inspectorSetupOpen = true;
        $this->inspectorSetupConfirmed = false;
    }

    public function openInspectorSetup(): void
    {
        $this->inspectorSetupOpen = true;
    }

    public function confirmInspectorSetup(): void
    {
        $this->validate([
            'inspectorId' => ['required','integer','exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer','exists:users,id','different:inspectorId'],
        ]);

        $this->inspectorSetupConfirmed = true;
        $this->inspectorSetupOpen = false;
    }

    public function resetInspector(): void
    {
        $this->coInspectorIds = [];
        $this->inspectorId = auth()->id();
        $this->inspectorSetupConfirmed = false;
        $this->inspectorSetupOpen = true;
    }

    public function searchAndOpen(): void
    {
        $this->reset(['foundAssetId', 'message', 'pendingOccurrenceId', 'availableChecklists', 'selectedChecklistId']);

        $code = trim($this->code);
        if ($code === '') {
            $this->message = __('asset_guard.quick_inspection.not_found');
            return;
        }

        $asset = AssetGuardAsset::query()->where('code', $code)->first();
        if ($asset === null) {
            $this->message = __('asset_guard.quick_inspection.not_found');
            return;
        }

        $this->foundAssetId = $asset->id;

        // Prefer an existing, not yet completed occurrence (do not create new)
        $occ = AssetGuardMaintenanceOccurrence::query()
            ->where('asset_id', $asset->id)
            ->whereNull('completed_at')
            ->orderBy('due_at')
            ->first();
        $this->pendingOccurrenceId = $occ?->id;

        // Resolve usable checklists (pre-use prioritized)
        $cls = AssetGuardInspectionChecklist::query()
            ->where('active', true)
            ->where(function ($q) use ($asset) {
                $q->where(function ($q2) use ($asset) {
                    $q2->where('applies_to', 'asset')->where('asset_id', $asset->id);
                })->orWhere(function ($q2) use ($asset) {
                    $q2->where('applies_to', 'asset_type')->where('asset_type_id', $asset->asset_type_id);
                });
            })
            ->orderByDesc('require_before_activation')
            ->orderBy('id')
            ->get(['id','name','require_before_activation'])
            ->map(fn($cl) => [
                'id' => (int) $cl->id,
                'name' => (string) $cl->name,
                'pre_use' => (bool) $cl->require_before_activation,
            ])->values()->all();

        $this->availableChecklists = $cls;
        $this->selectedChecklistId = $cls[0]['id'] ?? null;

        // Open wizard modal
        $this->showStartModal = true;
    }

    public function startSelectedInspection(): void
    {
        if (!$this->foundAssetId || !$this->inspectorId) {
            return;
        }

        if ($this->pendingOccurrenceId) {
            // Scheduled inspection via BatchPerformer
            $this->dispatch('open-batch-performer', occurrenceId: $this->pendingOccurrenceId, inspectorId: $this->inspectorId, coInspectorIds: $this->coInspectorIds);
            $this->showStartModal = false;
            return;
        }

        if (!$this->selectedChecklistId) {
            $this->message = __('asset_guard.quick_inspection.no_plan');
            return;
        }

        // Pre-use or ad-hoc checklist via PreUsePerformer (no occurrence creation)
        $this->dispatch('open-pre-use-performer', assetId: $this->foundAssetId, checklistId: $this->selectedChecklistId, inspectorId: $this->inspectorId, coInspectorIds: $this->coInspectorIds);
        $this->showStartModal = false;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.quick');
    }
}
