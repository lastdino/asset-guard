<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionItemResult, AssetGuardMaintenancePlan, AssetGuardInspectionChecklistItem, AssetGuardInspectionChecklist, AssetGuardAsset};
use Lastdino\AssetGuard\Services\InspectionScheduleCalculator;
use Lastdino\AssetGuard\Services\Inspections\{ChecklistOptionsService, InspectionDraftService, InspectionOutcomeService};
use Livewire\Attributes\On;
use Livewire\Component;
use Lastdino\AssetGuard\Services\PreUseInspectionGate;

class PerformerUnified extends Component
{
    public bool $open = false;

    public string $mode = '';

    public ?int $planId = null;
    public ?int $assetId = null;
    public ?int $checklistId = null;

    public ?int $inspectorId = null;
    /** @var array<int,int> */
    public array $coInspectorIds = [];

    /** @var array<int, array<string, mixed>> itemId => form */
    public array $forms = [];

    // Pre-use selection state
    /** @var array<int, array{id:int,name:string,pre_use:bool}> */
    public array $preuseOptions = [];
    public bool $selectingPreuse = false;

    protected $outcomes;
    protected $drafts;
    protected $options;

    public function boot(): void
    {
        // Resolve services from the container to avoid serializing objects into public state
        $this->outcomes = app(InspectionOutcomeService::class);
        $this->drafts   = app(InspectionDraftService::class);
        $this->options  = app(ChecklistOptionsService::class);
    }

    #[On('open-inspection')]
    public function openInspection(array $payload = []): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $this->mode = (string) ($payload['mode'] ?? '');
        $this->inspectorId = isset($payload['inspectorId']) ? (int) $payload['inspectorId'] : auth()->id();
        $this->coInspectorIds = is_array($payload['coInspectorIds'] ?? null) ? array_values(array_unique($payload['coInspectorIds'])) : [];
        if ($this->mode === 'preuse') {
            $this->assetId = (int) ($payload['assetId'] ?? 0);
            // 例: Gate 判定もここで集中管理
            if (! (new PreUseInspectionGate(assetId: $this->assetId))->isInspectionRequired()) {
                $this->dispatch('notify', type: 'info', message: __('asset-guard::inspections.pre_use_not_required_today'));
                return; // モーダルを開かない
            }
            $this->checklistId = isset($payload['checklistId']) ? (int) $payload['checklistId'] : null;
            $this->planId = null;
            $this->forms = [];
            $this->preuseOptions = [];
            $this->selectingPreuse = false;

            // If checklistId is not provided, load options and branch by count
            if (!$this->checklistId) {
                $opts = $this->loadPreuseOptions((int) $this->assetId);
                if (empty($opts)) {
                    $this->dispatch('notify', type: 'info', message: __('asset-guard::inspections.pre_use_not_required_today'));
                    return; // do not open modal
                }
                if (count($opts) === 1) {
                    $this->checklistId = (int) $opts[0]['id'];
                } else {
                    $this->preuseOptions = $opts;
                    $this->selectingPreuse = true;
                    $this->open = true;
                    return;
                }
            }

            // Build forms for the decided checklist
            $this->forms = $this->drafts->buildFormsForChecklist((int) $this->checklistId, $this->options);
            $ins = $this->inspectorId; $co = $this->coInspectorIds;
            $this->drafts->hydrateDraftBatch((int) $this->assetId, (int) $this->checklistId, $this->forms, $ins, $co);
            $this->inspectorId = $ins; $this->coInspectorIds = $co ?? [];

            $this->open = true;
            return;
        }

        if ($this->mode === 'plan-batch') {

            $this->planId = (int) ($payload['planId'] ?? 0);
            $plan = AssetGuardMaintenancePlan::query()->find($this->planId);
            $this->assetId = $plan ? (int) $plan->asset_id : null;
            $this->checklistId = $plan ? (int) $plan->checklist_id : null;


            $this->forms = $this->drafts->buildFormsForPlan((int) $this->planId, $this->options, $this->assetId, $this->checklistId);

            $ins = $this->inspectorId; $co = $this->coInspectorIds;
            if ($this->assetId && $this->checklistId) {
                $this->drafts->hydrateDraftBatch((int) $this->assetId, (int) $this->checklistId, $this->forms, $ins, $co);
                $this->inspectorId = $ins; $this->coInspectorIds = $co ?? [];
            }

            $this->open = true;
            return;
        }

        if ($this->mode === 'plan-single') {
            $this->planId = (int) ($payload['planId'] ?? 0);
            $checklistItemId = (int) ($payload['checklistItemId'] ?? 0);

            $plan = AssetGuardMaintenancePlan::query()->with(['asset'])->findOrFail($this->planId);
            $item = AssetGuardInspectionChecklistItem::query()->findOrFail($checklistItemId);

            $this->assetId = (int) $plan->asset_id;
            $this->checklistId = (int) $item->checklist_id;

            // Build a single-item form structure compatible with batch/preuse
            $this->forms = [
                $item->id => [
                    'name' => $item->name,
                    'method' => $item->method,
                    'result' => $item->method === 'boolean' ? 'Pass' : null,
                    'number' => null,
                    'text' => null,
                    'select' => null,
                    'note' => null,
                    'min' => $item->min_value,
                    'max' => $item->max_value,
                    'options' => $this->options->extract($item),
                    'media' => $item->getMedia('reference_photos')
                        ->map(static fn($m) => [
                            'id' => $m->id,
                            'file_name' => $m->file_name,
                        ])->all(),
                ],
            ];

            // Hydrate from any existing draft for this asset+checklist
            $ins = $this->inspectorId; $co = $this->coInspectorIds;
            $this->drafts->hydrateDraftBatch($this->assetId, $this->checklistId, $this->forms, $ins, $co);
            $this->inspectorId = $ins; $this->coInspectorIds = $co ?? [];

            $this->open = true;
            return;
        }
    }

    protected function rules(): array
    {
        $rules = [
            'inspectorId' => ['required','integer','exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer','exists:users,id','different:inspectorId'],
        ];

        foreach ($this->forms as $itemId => $form) {
            $path = "forms.$itemId";
            $method = $form['method'] ?? null;
            if ($method === 'boolean') {
                $rules["$path.result"] = ['required', Rule::in(['Pass','Fail'])];
            } elseif ($method === 'number') {
                $rules["$path.number"] = ['required','numeric', function (string $attr, $value, $fail) use ($form) {
                    if ($value === null || $value === '') { return; }
                    if (!is_null($form['min']) && $value < $form['min']) { $fail(__('asset-guard::validation.number_below_min')); }
                    if (!is_null($form['max']) && $value > $form['max']) { $fail(__('asset-guard::validation.number_above_max')); }
                }];
            } elseif ($method === 'text') {
                $rules["$path.text"] = ['required','string','max:2000'];
            } elseif ($method === 'select') {
                $rules["$path.select"] = ['required', Rule::in($form['options'] ?? [])];
            }
            $rules["$path.note"] = ['nullable','string','max:2000'];
        }

        return $rules;
    }

    public function saveDraftAll(): void
    {
        if (!$this->assetId || !$this->checklistId) { return; }
        $this->validate();

        $inspection = $this->drafts->upsertDraft((int) $this->assetId, (int) $this->checklistId, (int) $this->inspectorId, $this->coInspectorIds);

        foreach ($this->forms as $itemId => $form) {
            [$result, $value] = $this->outcomes->fromArray($form);
            AssetGuardInspectionItemResult::query()->updateOrCreate([
                'inspection_id' => $inspection->id,
                'checklist_item_id' => $itemId,
            ], [
                'result' => $result,
                'value' => $value,
                'note' => $form['note'] ?? null,
                'is_draft' => true,
            ]);
        }

        $this->dispatch('saved-draft');
    }

    public function finalizeAll(): void
    {

        if (!$this->assetId || !$this->checklistId) { return; }
        $this->validate();

        $inspection = $this->drafts->upsertDraft((int) $this->assetId, (int) $this->checklistId, (int) $this->inspectorId, $this->coInspectorIds);
        foreach ($this->forms as $itemId => $form) {
            [$result, $value] = $this->outcomes->fromArray($form);
            AssetGuardInspectionItemResult::query()->updateOrCreate([
                'inspection_id' => $inspection->id,
                'checklist_item_id' => $itemId,
            ], [
                'result' => $result,
                'value' => $value,
                'note' => $form['note'] ?? null,
                'is_draft' => false,
            ]);
        }

        $inspection->update(['status' => 'Completed', 'performed_at' => Carbon::now()]);

        // Plan handling after completion
        if ($this->planId) {
            $plan = AssetGuardMaintenancePlan::query()->with('checklist')->find($this->planId);
            if ($plan) {
                // Mark current plan completed
                $plan->update([
                    'status' => 'Completed',
                    'completed_at' => Carbon::now(),
                ]);

                $cl = $plan->checklist;
                $isPerUse = ($cl && (bool) ($cl->require_before_activation ?? false));
                if (! $isPerUse && $cl && $cl->frequency_unit) {
                    $mult = max(1, (int) ($cl->frequency_value ?? 1));
                    $base = Carbon::parse($plan->scheduled_at ?? Carbon::now());
                    $next = InspectionScheduleCalculator::nextDueDate($cl->frequency_unit, $mult, $base);
                    if ($next) {
                        if ($this->mode === 'plan-batch') {
                            // Batch: create a new plan occurrence
                            AssetGuardMaintenancePlan::query()->create([
                                'asset_id' => $plan->asset_id,
                                'checklist_id' => $plan->checklist_id,
                                'title' => $plan->title,
                                'description' => $plan->description,
                                'scheduled_at' => $next,
                                'timezone' => $plan->timezone ?? config('app.timezone'),
                                'lead_time_days' => $plan->lead_time_days,
                                'assigned_to' => $plan->assigned_to,
                                'status' => 'Scheduled',
                            ]);
                        } elseif ($this->mode === 'plan-single') {
                            // Single-item: update the same plan to next schedule (legacy behavior)
                            $plan->update([
                                'scheduled_at' => $next->toDateTimeString(),
                                'status' => 'Scheduled',
                                'due_at' => null,
                            ]);
                        }
                    }
                }
            }
        }

        $this->open = false;
        $this->dispatch('executed');
        $this->dispatch('refresh')->to(Index::class);
    }

    /**
     * Load pre-use checklist options for an asset, excluding those completed today.
     * @return array<int, array{id:int,name:string,pre_use:bool}>
     */
    protected function loadPreuseOptions(int $assetId): array
    {
        if ($assetId <= 0) { return []; }
        $asset = AssetGuardAsset::query()->find($assetId);
        if (! $asset) { return []; }

        $cls = AssetGuardInspectionChecklist::query()
            ->where('require_before_activation', true)
            ->where('active', true)
            ->where(function ($q) use ($asset) {
                $q->where(function ($q2) use ($asset) {
                    $q2->where('applies_to', 'asset')->where('asset_id', $asset->id);
                })->orWhere(function ($q2) use ($asset) {
                    $q2->where('applies_to', 'asset_type')->where('asset_type_id', $asset->asset_type_id);
                });
            })
            ->get(['id','name','require_before_activation']);

        if ($cls->isEmpty()) { return []; }

        $today = now(config('app.timezone'))->toDateString();
        $doneIds = AssetGuardInspection::query()
            ->where('asset_id', $asset->id)
            ->whereIn('checklist_id', $cls->pluck('id'))
            ->where('status', 'Completed')
            ->whereDate('performed_at', $today)
            ->pluck('checklist_id')
            ->unique()
            ->all();

        return $cls->reject(fn($c) => in_array($c->id, $doneIds, true))
            ->map(fn($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'pre_use' => true,
            ])->values()->all();
    }

    public function selectChecklist(int $checklistId): void
    {
        if (! $this->assetId) { return; }
        $this->checklistId = $checklistId;
        $this->forms = $this->drafts->buildFormsForChecklist((int) $this->checklistId, $this->options);
        $ins = $this->inspectorId; $co = $this->coInspectorIds;
        $this->drafts->hydrateDraftBatch((int) $this->assetId, (int) $this->checklistId, $this->forms, $ins, $co);
        $this->inspectorId = $ins; $this->coInspectorIds = $co ?? [];
        $this->selectingPreuse = false;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.performer-unified');
    }
}
