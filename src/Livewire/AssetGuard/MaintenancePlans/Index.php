<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\MaintenancePlans;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public ?int $assetId = null;

    public array $events = [];

    // Modal state
    public bool $showPlan = false;
    public bool $showShow = false;

    public ?int $viewingListId = null;
    public ?int $editingPlanId = null;

    public $infos;

    // Integrated show state
    public ?AssetGuardInspectionChecklist $viewingList = null;

    // Integrated edit/create form state
    public ?AssetGuardMaintenancePlan $editingPlan = null;
    public array $form = [
        'asset_id' => null,
        'checklist_id' => null,
        'title' => '',
        'description' => null,
        'scheduled_at' => '',
        'due_at' => null,
        'completed_at' => null,
        'lead_time_days' => 3,
        'assigned_to' => null,
        'status' => 'Scheduled',
    ];

    public function mount(): void
    {
        //$this->loadCalendar();
    }

    #[On('calendar-dropped')]
    public function reschedule(int $planId, string $newPlannedAt): void
    {
        $occ = AssetGuardMaintenancePlan::query()->findOrFail($planId);
        $occ->scheduled_at = Carbon::parse($newPlannedAt);
        $occ->save();

        $this->dispatch('notify', message: 'Rescheduled');
        $this->loadCalendar($this->infos);
        $this->dispatch('refreshCalendar');
    }

    public function loadCalendar($info)
    {
        $this->infos = $info;
        $query = AssetGuardMaintenancePlan::query()->with([ 'asset'])
            ->whereBetween('scheduled_at', [$info['start'], $info['end']])
            ->whereNotIN('status', ['Completed', 'Cancelled', 'Archived'])
            ->when($this->assetId, fn($q) => $q->where('asset_id', $this->assetId));

        $this->events = $query->latest('scheduled_at')->limit(500)->get()->map(function ($o): array {
            return [
                'id' => $o->id,
                'title' => optional($o->asset)->name.' · '.($o->title ?? 'Plan'),
                'start' => $o->scheduled_at?->toIso8601String(),
                // Keep URL for non-JS fallback
                //'url' => route('asset-guard.maintenance-plans.show', $o->maintenance_plan_id),
                // Provide planId to open modal
                'planId' => $o->id,
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

    public function getListsProperty()
    {
        return AssetGuardInspectionChecklist::query()
            ->where('active', true)
            ->where(function ($query)  {
                $query->when($this->assetId, function ($q) {
                    $q->where('asset_id', $this->assetId)
                        ->orWhereExists(function ($subquery) {
                            $subquery->select('id')
                                ->from('asset_guard_assets')
                                ->whereColumn('asset_guard_inspection_checklists.asset_type_id', 'asset_guard_assets.asset_type_id')
                                ->where('asset_guard_assets.id', $this->assetId)
                                ->where('asset_guard_inspection_checklists.applies_to', 'asset_type');
                        });
                });
            })
            ->with(['asset', 'assetType'])
            ->get();
    }

    public function openShow(int $listId): void
    {
        $this->viewingListId = $listId;
        $this->viewingList = AssetGuardInspectionChecklist::query()
            ->with('asset')
            ->findOrFail($listId);


        $this->showShow = true;
    }

    public function openPlanCreate(): void
    {
        $this->editingPlanId = null;

        $this->form =  [
            'asset_id' => $this->assetId,
            'checklist_id' => $this->viewingListId,
            'title' => '',
            'description' => null,
            'scheduled_at' => '',
            'due_at' => null,
            'completed_at' => null,
            'lead_time_days' => 3,
            'assigned_to' => null,
            'status' => 'Scheduled',
        ];
        $this->showPlan = true;
    }

    public function openPlanEdit(int $planId): void
    {
        $model = AssetGuardMaintenancePlan::query()->with('asset')->findOrFail($planId);

        $this->editingPlanId = $planId;
        $this->editingPlan = $model;

        $this->form =  [
            'asset_id' => $model->asset_id,
            'checklist_id' => $model->checklist_id,
            'title' => (string) ($model->title ?? ''),
            'description' => $model->description,
            'scheduled_at' => optional($model->scheduled_at)->toDateString() ?? '',
            'due_at' => optional($model->due_at)->toDateString(),
            'timezone' => (string) ($model->timezone ?: config('app.timezone')),
            'lead_time_days' => (int) ($model->lead_time_days ?? 3),
            'assigned_to' => $model->assigned_to,
            'status' => (string) ($model->status ?: 'Scheduled'),
        ];
        $this->showPlan = true;
    }

    public function openPlanCreateFromCalendar(string $date): void
    {
        $this->openPlanCreate();
        $this->form['scheduled_at'] = Carbon::parse($date)->toDateString();
    }

    public function deletePlan(int $planId): void
    {
        $plan = AssetGuardMaintenancePlan::query()->findOrFail($planId);
        $plan->delete();
        $this->dispatch('notify', message: __('asset-guard::plans.deleted'));
    }

    protected function rules(): array
    {
        return [
            'form.asset_id' => ['required', Rule::exists('asset_guard_assets', 'id')],
            'form.checklist_id' => [
                'required',
                Rule::exists('asset_guard_inspection_checklists', 'id')
            ],
            'form.title' => ['nullable', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.scheduled_at' => [
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
            'form.due_at' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.completed_at' => ['nullable', 'date'],
            'form.timezone' => ['required', 'string'],
            'form.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'form.assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'form.status' => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        // Auto-generate title
        $this->form['title'] = $this->form['scheduled_at']."/".$this->form['assigned_to'];
        AssetGuardMaintenancePlan::updateOrCreate(['id'=>$this->editingPlanId],$this->form);
        $this->dispatch('saved');
    }

    public function getAssetsProperty()
    {
        return AssetGuardAsset::query()
            ->when(! empty($this->form['asset_id']), fn($q) => $q->where('id', $this->form['asset_id']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[On('saved')]
    public function onPlanSaved(): void
    {
        $this->closeModals();
        $this->loadCalendar($this->infos);
        $this->dispatch('refreshCalendar');
    }

    public function closeModals(): void
    {
        $this->reset(['showPlan','showShow', 'viewingListId', 'editingPlanId', 'viewingList', 'editingPlan']);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.maintenance-plans.index');
    }
}
