<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\MaintenancePlans;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Lastdino\AssetGuard\Services\GenerateMaintenanceOccurrences;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public ?int $assetId = null;

    public array $events = [];

    // Modal state
    public bool $showCreate = false;
    public bool $showShow = false;
    public bool $showEdit = false;
    public ?int $viewingPlanId = null;
    public ?int $editingPlanId = null;

    public $infos;

    // Integrated show state
    public ?AssetGuardMaintenancePlan $viewingPlan = null;
    public array $upcomingOccurrences = [];

    // Integrated edit/create form state
    public ?AssetGuardMaintenancePlan $editingPlan = null;
    public array $form = [
        'asset_id' => null,
        'checklist_id' => null,
        'title' => '',
        'description' => null,
        'start_date' => '',
        'end_date' => null,
        'timezone' => '',
        'lead_time_days' => 3,
        'assigned_to' => null,
        'status' => 'Scheduled',
        'require_before_activation' => false,
    ];

    // Occurrence edit modal state
    public bool $showOccurrenceEdit = false;
    public ?int $editingOccurrenceId = null;
    public array $occurrenceForm = [
        'planned_at' => '',
        'due_at' => null,
        'status' => 'Scheduled',
        'notes' => null,
        'completed_at' => null,
        'assigned_to' => null,
    ];

    // Occurrence delete confirm state
    public bool $showOccurrenceDelete = false;
    public ?int $deletingOccurrenceId = null;

    public function mount(): void
    {
        //$this->loadCalendar();
    }

    #[On('calendar-dropped')]
    public function reschedule(int $occurrenceId, string $newPlannedAt): void
    {


        $occ = Occurrence::query()->with('plan')->findOrFail($occurrenceId);
        $occ->planned_at = Carbon::parse($newPlannedAt);
        $occ->save();

        $this->dispatch('notify', message: 'Rescheduled');
        $this->loadCalendar($this->infos);
        $this->dispatch('refreshCalendar');
    }

    public function loadCalendar($info)
    {
        $this->infos = $info;
        $query = Occurrence::query()->with(['plan', 'asset'])
            ->whereBetween('planned_at', [$info['start'], $info['end']])
            ->when($this->assetId, fn($q) => $q->where('asset_id', $this->assetId));

        $this->events = $query->latest('planned_at')->limit(500)->get()->map(function ($o): array {
            return [
                'id' => $o->id,
                'title' => optional($o->asset)->name.' · '.($o->plan->title ?? 'Plan'),
                'start' => $o->planned_at?->toIso8601String(),
                // Keep URL for non-JS fallback
                //'url' => route('asset-guard.maintenance-plans.show', $o->maintenance_plan_id),
                // Provide planId to open modal
                'planId' => $o->maintenance_plan_id,
            ];
        })->all();

        return $this->events;

    }


    // 追加: 設備フィルタが変わったら自動で再取得
    public function updatedAssetId(): void
    {
        $this->loadCalendar($this->infos);
        $this->dispatch('refreshCalendar');
    }

    // 設備選択変更時にチェックリスト選択をクリア
    public function updatedFormAssetId($value): void
    {
        $this->form['checklist_id'] = null;
    }

    public function getPlansProperty()
    {
        return AssetGuardMaintenancePlan::query()
            ->with('asset')
            ->when($this->assetId, fn($q) => $q->where('asset_id', $this->assetId))
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    // Modal actions
    public function openCreate(): void
    {
        $this->prepareEditContext(null, $this->assetId);
        $this->showCreate = true;
    }

    public function openShow(int $planId): void
    {
        $this->viewingPlanId = $planId;
        $this->viewingPlan = AssetGuardMaintenancePlan::query()
            ->with('asset')
            ->findOrFail($planId);

        $this->upcomingOccurrences = \Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence::query()
            ->with('asset')
            ->where('maintenance_plan_id', $planId)
            ->whereNull('completed_at')
            ->orderBy('planned_at')
            ->limit(50)
            ->get()
            ->toArray();

        $this->showShow = true;
    }

    public function openEdit(int $planId): void
    {
        $this->prepareEditContext($planId, null);
        $this->showEdit = true;
    }

    private function prepareEditContext(?int $planId, ?int $assetContextId = null): void
    {
        $model = $planId
            ? AssetGuardMaintenancePlan::query()->findOrFail($planId)
            : new AssetGuardMaintenancePlan([
                'timezone' => (string) config('app.timezone'),
                'lead_time_days' => 3,
                'status' => 'Scheduled',
            ]);

        $this->editingPlan = $model;

        $this->form = [
            'asset_id' => $model->asset_id,
            'checklist_id' => $model->checklist_id,
            'title' => (string) ($model->title ?? ''),
            'description' => $model->description,
            'start_date' => optional($model->start_date)->toDateString() ?? '',
            'end_date' => optional($model->end_date)->toDateString(),
            'timezone' => (string) ($model->timezone ?: config('app.timezone')),
            'lead_time_days' => (int) ($model->lead_time_days ?? 3),
            'assigned_to' => $model->assigned_to,
            'status' => (string) ($model->status ?: 'Scheduled'),
            'require_before_activation' => (bool) ($model->require_before_activation ?? false),
        ];

        // 新規作成時のみ、設備コンテキストをプリセット
        if (! $model->exists && $assetContextId) {
            $this->form['asset_id'] = $assetContextId;
        }
    }

    #[On('open-occurrence-show')]
    public function openOccurrenceEdit(int $occurrenceId): void
    {
        $occ = Occurrence::query()->findOrFail($occurrenceId);

        $this->editingOccurrenceId = $occ->id;
        $this->occurrenceForm = [
            'planned_at' => optional($occ->planned_at)->format('Y-m-d\TH:i') ?? '',
            'due_at' => optional($occ->due_at)->format('Y-m-d\TH:i'),
            'status' => (string) ($occ->status ?: 'Scheduled'),
            'notes' => $occ->notes,
            'completed_at' => optional($occ->completed_at)->format('Y-m-d\TH:i'),
            'assigned_to' => $occ->assigned_to,
        ];

        $this->showOccurrenceEdit = true;
    }

    public function saveOccurrence(): void
    {

        $this->validate([
            'occurrenceForm.planned_at' => ['required', 'date'],
            'occurrenceForm.due_at' => ['nullable', 'date', 'after_or_equal:occurrenceForm.planned_at'],
            'occurrenceForm.status' => ['required', 'string'],
            'occurrenceForm.notes' => ['nullable', 'string'],
            'occurrenceForm.completed_at' => ['nullable', 'date', 'after_or_equal:occurrenceForm.planned_at'],
            'occurrenceForm.assigned_to' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $occ = Occurrence::query()->findOrFail((int) $this->editingOccurrenceId);

        $occ->planned_at = Carbon::parse($this->occurrenceForm['planned_at']);
        $occ->due_at = ! empty($this->occurrenceForm['due_at']) ? Carbon::parse((string) $this->occurrenceForm['due_at']) : null;
        $occ->status = (string) $this->occurrenceForm['status'];
        $occ->notes = $this->occurrenceForm['notes'];
        $occ->assigned_to = $this->occurrenceForm['assigned_to'];

        if ($occ->status === 'Completed') {
            $occ->completed_at = ! empty($this->occurrenceForm['completed_at'])
                ? Carbon::parse((string) $this->occurrenceForm['completed_at'])
                : now();
        } else {
            $occ->completed_at = ! empty($this->occurrenceForm['completed_at'])
                ? Carbon::parse((string) $this->occurrenceForm['completed_at'])
                : null;
        }

        $occ->save();

        if ($this->viewingPlanId) {
            $this->openShow($this->viewingPlanId);
        }
        if ($this->infos) {
            $this->loadCalendar($this->infos);
            $this->dispatch('refreshCalendar');
        }

        $this->dispatch('notify', message: __('asset-guard::occurrences.saved'));
        $this->showOccurrenceEdit = false;
    }

    public function confirmDeleteOccurrence(int $occurrenceId): void
    {
        $this->deletingOccurrenceId = $occurrenceId;
        $this->showOccurrenceDelete = true;
    }

    public function deleteOccurrence(): void
    {

        $occ = Occurrence::query()->findOrFail((int) $this->deletingOccurrenceId);
        $planId = $occ->maintenance_plan_id;
        $occ->delete();

        // Refresh lists and calendar
        if ($this->viewingPlanId) {
            $this->openShow($this->viewingPlanId);
        } elseif ($planId) {
            // Ensure upcoming occurrences for the plan are consistent even if modal not open
            $this->viewingPlanId = $planId;
            $this->openShow($planId);
        }

        if ($this->infos) {
            $this->loadCalendar($this->infos);
            $this->dispatch('refreshCalendar');
        }

        $this->dispatch('notify', message: __('asset-guard::occurrences.deleted'));
        $this->showOccurrenceDelete = false;
        $this->deletingOccurrenceId = null;
    }

    protected function rules(): array
    {
        return [
            'form.asset_id' => ['required', Rule::exists('asset_guard_assets', 'id')],
            'form.checklist_id' => [
                'required',
                Rule::exists('asset_guard_inspection_checklists', 'id')
                    ->where(fn($q) => $q->where('asset_id', $this->form['asset_id'] ?? 0)),
            ],
            'form.title' => ['nullable', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.start_date' => [
                'nullable',
                'date',
                Rule::requiredIf(function (): bool {
                    $cid = $this->form['checklist_id'] ?? null;
                    if (! $cid) {
                        return true; // checklist required above, but defend anyway
                    }
                    $cl = AssetGuardInspectionChecklist::find($cid);
                    // Require start_date unless checklist frequency is PerUse
                    return ! ($cl && $cl->frequency_unit === 'PerUse');
                }),
            ],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.timezone' => ['required', 'string'],
            'form.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'form.assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'form.status' => ['required', 'string'],
            'form.require_before_activation' => ['nullable','boolean'],
        ];
    }

    public function save(): void
    {

        $this->validate();

        // Auto-generate title
        $this->form['title'] = $this->makeDefaultTitle();

        // Auto-set trigger_type based on checklist frequency
        $trigger = 'time';
        if (! empty($this->form['checklist_id'])) {
            $cl = AssetGuardInspectionChecklist::find($this->form['checklist_id']);
            if ($cl && $cl->frequency_unit === 'PerUse') {
                $trigger = 'per_use';
            }
        }

        $plan = $this->editingPlan ?? new AssetGuardMaintenancePlan();
        $plan->fill($this->form);
        $plan->trigger_type = $trigger;
        $plan->save();

        $this->editingPlan = $plan; // ensure id

        // Generate (or top-up) future occurrences
        (new GenerateMaintenanceOccurrences())->handle($plan);

        $this->dispatch('saved');
    }

    private function makeDefaultTitle(): string
    {
        $asset = $this->editingPlan && $this->editingPlan->exists
            ? ($this->editingPlan->relationLoaded('asset') ? $this->editingPlan->asset : $this->editingPlan->asset()->first())
            : ($this->form['asset_id'] ? AssetGuardAsset::find($this->form['asset_id']) : null);

        $checklist = $this->editingPlan && $this->editingPlan->exists
            ? ($this->editingPlan->relationLoaded('checklist') ? $this->editingPlan->checklist : $this->editingPlan->checklist()->first())
            : ($this->form['checklist_id'] ? AssetGuardInspectionChecklist::find($this->form['checklist_id']) : null);

        $assetPart = trim(implode(' ', array_filter([
            $asset?->code,
            $asset?->name,
        ])));

        $freqUnit = $checklist?->frequency_unit ?: null;
        $freqVal = (int) ($checklist?->frequency_value ?? 1);
        $freqLabel = $freqUnit === 'PerUse'
            ? __('asset-guard::plans.require_before_activation')
            : ($freqUnit ? ($freqVal > 1 ? ($freqVal . 'x ' . $freqUnit) : $freqUnit) : null);

        $parts = array_filter([
            $assetPart ?: null,
            $checklist?->name,
            $freqLabel ? '(' . $freqLabel . ')' : null,
        ]);

        return $parts ? implode(' — ', array_filter([$assetPart, $checklist?->name])) . ($freqLabel ? ' ' . '(' . $freqLabel . ')' : '') : 'Maintenance Plan';
    }

    public function getAssetsProperty()
    {
        return AssetGuardAsset::query()
            ->when(! empty($this->form['asset_id']), fn($q) => $q->where('id', $this->form['asset_id']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getChecklistsProperty()
    {
        return AssetGuardInspectionChecklist::query()
            ->when(! empty($this->form['asset_id']), fn($q) => $q->where('asset_id', $this->form['asset_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'frequency_unit', 'frequency_value']);
    }

    #[On('saved')]
    public function onPlanSaved(): void
    {
        $this->closeModals();
        $this->loadCalendar($this->infos);
        $this->dispatch('refreshCalendar');
    }

    #[On('open-plan-show')]
    public function onOpenPlanShow(int $planId): void
    {
        $this->openShow($planId);
    }

    public function closeModals(): void
    {
        $this->reset(['showCreate', 'showShow', 'showEdit', 'viewingPlanId', 'editingPlanId', 'viewingPlan', 'editingPlan']);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.maintenance-plans.index');
    }
}
