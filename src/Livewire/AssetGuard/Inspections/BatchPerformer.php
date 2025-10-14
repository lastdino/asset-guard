<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Jobs\ComputeInspectionSchedules;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionItemResult, AssetGuardMaintenanceOccurrence};
use Livewire\Component;
use Livewire\WithFileUploads;

class BatchPerformer extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?int $occurrenceId = null;
    public ?int $assetId = null;
    public ?int $checklistId = null;

    public ?int $inspectorId = null;
    public array $coInspectorIds = [];

    /** @var array<int, array<string, mixed>> itemId => form */
    public array $forms = [];

    protected $listeners = ['open-batch-performer' => 'openFor'];

    public function openFor(int $occurrenceId, ?int $inspectorId = null, array $coInspectorIds = []): void
    {
        $this->reset(['forms','coInspectorIds']);

        $occ = AssetGuardMaintenanceOccurrence::query()
            ->with('plan.checklist.items', 'asset')
            ->findOrFail($occurrenceId);

        $this->occurrenceId = $occ->id;
        $this->assetId = $occ->asset_id;
        $this->checklistId = $occ->plan?->checklist?->id;
        $this->inspectorId = $inspectorId ?? auth()->id();
        $this->coInspectorIds = array_values(array_unique($coInspectorIds));

        $items = $occ->plan?->checklist?->items ?? collect();
        foreach ($items as $item) {
            $this->forms[$item->id] = [
                'name' => $item->name,
                'method' => $item->method,
                'result' => $item->method === 'boolean' ? 'Pass' : null,
                'number' => null,
                'text' => null,
                'select' => null,
                'note' => null,
                'min' => $item->min_value,
                'max' => $item->max_value,
                'options' => $this->extractOptions($item),
                'media' => $item->getMedia('reference_photos'),
            ];
        }

        // hydrate from existing draft if present
        $this->hydrateFromExistingDraft();

        $this->open = true;
    }

    protected function rules(): array
    {
        $rules = [
            'inspectorId' => ['required','integer','exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer','exists:users,id','different:inspectorId'],
        ];

        // dynamic per-item rules
        foreach ($this->forms as $itemId => $form) {
            $method = $form['method'] ?? null;
            if ($method === 'boolean') {
                $rules["forms.$itemId.result"] = ['required', Rule::in(['Pass','Fail'])];
            } elseif ($method === 'number') {
                $rules["forms.$itemId.number"] = ['required','numeric', function (string $attr, $value, $fail) use ($form) {
                    if ($value === null || $value === '') { return; }
                    if (!is_null($form['min']) && $value < $form['min']) { $fail('下限値を下回っています。'); }
                    if (!is_null($form['max']) && $value > $form['max']) { $fail('上限値を上回っています。'); }
                }];
            } elseif ($method === 'text') {
                $rules["forms.$itemId.text"] = ['required','string','max:2000'];
            } elseif ($method === 'select') {
                $rules["forms.$itemId.select"] = ['required', Rule::in($form['options'] ?? [])];
            }
            $rules["forms.$itemId.note"] = ['nullable','string','max:2000'];
        }

        return $rules;
    }

    protected function upsertDraftInspection(): AssetGuardInspection
    {
        $inspection = AssetGuardInspection::query()->firstOrCreate([
            'asset_id' => $this->assetId,
            'checklist_id' => $this->checklistId,
            'status' => 'Draft',
        ], [
            'performed_by_user_id' => $this->inspectorId,
            'performed_at' => Carbon::now(),
        ]);

        $inspection->update(['performed_by_user_id' => $this->inspectorId]);
        $sync = collect($this->coInspectorIds)->unique()->values()->mapWithKeys(fn($id) => [$id => ['role' => 'Assistant']])->all();
        $inspection->inspectors()->sync($sync + [
            $this->inspectorId => ['role' => 'Primary']
        ]);

        return $inspection;
    }

    public function saveDraftAll(): void
    {
        if (!$this->assetId || !$this->checklistId) { return; }
        $this->validate();

        $inspection = $this->upsertDraftInspection();

        foreach ($this->forms as $itemId => $form) {
            [$result, $value] = $this->buildOutcome($form);
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
        if (!$this->assetId || !$this->checklistId || !$this->occurrenceId) { return; }
        $this->validate();

        $inspection = $this->upsertDraftInspection();

        // finalize results
        foreach ($this->forms as $itemId => $form) {
            [$result, $value] = $this->buildOutcome($form);
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

        // Complete current occurrence and create next occurrence based on checklist frequency
        $occ = AssetGuardMaintenanceOccurrence::query()->with('plan.checklist')->findOrFail($this->occurrenceId);
        $occ->update([
            'status' => 'Completed',
            'completed_at' => Carbon::now(),
        ]);

        $cl = $occ->plan?->checklist;
        if ($cl && $cl->frequency_unit) {
            $mult = (int) ($cl->frequency_value ?? 1);
            $base = Carbon::parse($occ->planned_at);
            $next = ComputeInspectionSchedules::nextDueDate($cl->frequency_unit, max(1, $mult), $base);
            if ($next) {
                AssetGuardMaintenanceOccurrence::query()->firstOrCreate([
                    'maintenance_plan_id' => $occ->maintenance_plan_id,
                    'asset_id' => $occ->asset_id,
                    'planned_at' => $next->toDateTimeString(),
                ], [
                    'status' => 'Scheduled',
                ]);
            }
        }

        $this->open = false;
        $this->dispatch('executed');
        $this->dispatch('refresh')->to(Index::class);
    }

    protected function buildOutcome(array $form): array
    {
        $method = $form['method'] ?? null;
        if ($method === 'number') {
            $value = isset($form['number']) && $form['number'] !== '' ? (float) $form['number'] : null;
            if ($value === null) {
                return ['Pass', null];
            }
            $pass = true;
            if (!is_null($form['min'])) { $pass = $pass && $value >= $form['min']; }
            if (!is_null($form['max'])) { $pass = $pass && $value <= $form['max']; }
            return [$pass ? 'Pass' : 'Fail', (string) $value];
        }

        return match ($method) {
            'boolean' => [($form['result'] ?? 'Pass'), null],
            'text'    => ['Pass', $form['text'] ?? null],
            'select'  => ['Pass', $form['select'] ?? null],
            default   => ['Pass', null],
        };
    }

    protected function extractOptions($item): array
    {
        if ($item && property_exists($item, 'choices') && $item->choices) {
            return collect(explode(',', (string) $item->choices))
                ->map(fn($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        }
        return [];
    }

    protected function hydrateFromExistingDraft(): void
    {
        if (!$this->assetId || !$this->checklistId) { return; }
        $draft = AssetGuardInspection::query()
            ->where('asset_id', $this->assetId)
            ->where('checklist_id', $this->checklistId)
            ->where('status', 'Draft')
            ->latest('id')
            ->first();

        if (!$draft) { return; }

        // inspectors
        $this->inspectorId = $draft->performed_by_user_id ?: $this->inspectorId;
        $this->coInspectorIds = $draft->inspectors()->wherePivot('role', 'Assistant')->pluck('users.id')->all();

        // results
        $existing = AssetGuardInspectionItemResult::query()->where('inspection_id', $draft->id)->get();
        foreach ($existing as $res) {
            if (!isset($this->forms[$res->checklist_item_id])) { continue; }
            $method = $this->forms[$res->checklist_item_id]['method'] ?? null;
            $this->forms[$res->checklist_item_id]['note'] = $res->note;
            if ($method === 'boolean') {
                $this->forms[$res->checklist_item_id]['result'] = $res->result ?: 'Pass';
            } elseif ($method === 'number') {
                $this->forms[$res->checklist_item_id]['number'] = is_numeric($res->value ?? null) ? (float) $res->value : null;
            } elseif ($method === 'text') {
                $this->forms[$res->checklist_item_id]['text'] = $res->value;
            } elseif ($method === 'select') {
                $this->forms[$res->checklist_item_id]['select'] = $res->value;
            }
        }
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.batch-performer');
    }
}
