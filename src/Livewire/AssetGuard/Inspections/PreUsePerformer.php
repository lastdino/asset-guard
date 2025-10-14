<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionChecklist, AssetGuardInspectionChecklistItem, AssetGuardInspectionItemResult};
use Livewire\Attributes\On;
use Livewire\Component;

class PreUsePerformer extends Component
{
    public bool $open = false;

    public ?int $assetId = null;
    public ?int $checklistId = null;

    public ?int $inspectorId = null;
    public array $coInspectorIds = [];


    /** @var array<string, array{result:?string, number: mixed, text:?string, select:?string, note:?string}> */
    public array $form = [];

    // Back-compat summary fields (not used for saving when items exist)
    public string $result = 'Pass'; // Pass|Fail
    public ?string $note = null;

    #[On('open-pre-use-performer')]
    public function openFor(int $assetId, int $checklistId, ?int $inspectorId = null, array $coInspectorIds = []): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $this->assetId = $assetId;
        $this->checklistId = $checklistId;
        $this->inspectorId = $inspectorId ?? auth()->id();
        $this->coInspectorIds = array_values(array_unique($coInspectorIds));
        $this->result = 'Pass';
        $this->note = null;
        $this->form = [];

        // Load checklist items ordered (by sort_order then id)
        $checklist = AssetGuardInspectionChecklist::query()
            ->with(['items' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->findOrFail($checklistId);

        foreach ($checklist->items as $item) {
            $this->form[$item->id] = [
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

        // Hydrate from existing draft if present
        $this->hydrateFromDraft();

        $this->open = true;
    }

    protected function rules(): array
    {
        $rules = [
            'inspectorId' => ['required','integer','exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer','exists:users,id','different:inspectorId'],
        ];

        return $rules;
    }

    public function finalizeAll(): void
    {
        if (!$this->assetId || !$this->checklistId) { return; }
        $this->validate();

        $inspection = $this->upsertDraftInspection();

        // finalize results
        foreach ($this->form as $itemId => $form) {
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

        // Update UI: close modal and inform parent/listeners
        $this->open = false;
        $this->dispatch('pre-use-inspection-finished');
        $this->dispatch('notify', type: 'success', message: __('asset-guard::inspections.save_and_finalize'));
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

    /**
     * Load existing Draft inspection (if any) and hydrate $note and $form.
     */
    protected function hydrateFromDraft(): void
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
            if (!isset($this->form[$res->checklist_item_id])) { continue; }
            $method = $this->form[$res->checklist_item_id]['method'] ?? null;
            $this->form[$res->checklist_item_id]['note'] = $res->note;
            if ($method === 'boolean') {
                $this->form[$res->checklist_item_id]['result'] = $res->result ?: 'Pass';
            } elseif ($method === 'number') {
                $this->form[$res->checklist_item_id]['number'] = is_numeric($res->value ?? null) ? (float) $res->value : null;
            } elseif ($method === 'text') {
                $this->form[$res->checklist_item_id]['text'] = $res->value;
            } elseif ($method === 'select') {
                $this->form[$res->checklist_item_id]['select'] = $res->value;
            }
        }
    }

    /**
     * Save current inputs as a Draft inspection and keep modal open.
     */
    public function saveDraft(): void
    {
        // Basic validation for IDs and inspector
        $this->validate([
            'assetId' => ['required','integer'],
            'checklistId' => ['required','integer'],
            'inspectorId' => ['required','integer','exists:users,id'],
            'note' => ['nullable','string','max:2000'],
        ]);

        // Upsert draft header
        $inspection = $this->upsertDraftInspection();

        foreach ($this->form as $itemId => $form) {
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

        $this->dispatch('notify', type: 'success', message: __('asset-guard::inspections.save_draft'));
        // Keep modal open for continued editing
    }

    /**
     * @return array<int, string>
     */
    protected function extractOptions(AssetGuardInspectionChecklistItem $item): array
    {
        $options = [];
        $pc = $item->pass_condition;
        if (is_array($pc) && isset($pc['options']) && is_array($pc['options'])) {
            $options = array_values(array_filter(array_map('strval', $pc['options'])));
        }
        return $options;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.pre-use-performer');
    }
}
