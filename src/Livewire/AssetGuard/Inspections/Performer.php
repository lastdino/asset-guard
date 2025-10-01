<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Jobs\ComputeInspectionSchedules;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionItemResult, AssetGuardMaintenanceOccurrence, AssetGuardInspectionChecklistItem};
use Livewire\Component;
use Livewire\WithFileUploads;

class Performer extends Component
{
    use WithFileUploads;

    public bool $open = false;
    public ?int $occurrenceId = null;
    public ?int $checklistItemId = null;

    public ?string $assetLabel = null;
    public ?string $itemName = null;
    public ?string $method = null;
    public ?float $minValue = null;
    public ?float $maxValue = null;
    public array $options = [];

    public ?string $result = null;
    public ?string $text = null;
    public ?string $note = null;
    public $number = null;
    public ?string $select = null;

    /** Inspector fields */
    public ?int $inspectorId = null;
    public array $coInspectorIds = [];

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public $medias = [];

    protected $listeners = ['open-performer' => 'openFor'];

    public function openFor(int $occurrenceId, int $checklistItemId): void
    {
        $occurrence = AssetGuardMaintenanceOccurrence::query()
            ->with(['asset', 'plan.checklist'])
            ->findOrFail($occurrenceId);
        $item = AssetGuardInspectionChecklistItem::query()->findOrFail($checklistItemId);

        $this->reset(['result','text','note','attachments','number','select','coInspectorIds']);

        $this->occurrenceId = $occurrence->id;
        $this->checklistItemId = $item->id;
        $this->assetLabel = ($occurrence->asset?->code ?? '') . ' — ' . ($occurrence->asset?->name ?? '');
        $this->itemName = $item->name;
        $this->method = $item->method;
        $this->minValue = $item->min_value !== null ? (float) $item->min_value : null;
        $this->maxValue = $item->max_value !== null ? (float) $item->max_value : null;
        $this->options = $this->extractOptions($item);

        $this->inspectorId = auth()->id();

        if ($this->method === 'boolean') {
            $this->result = 'Pass';
        }
        $this->medias= $item->getMedia('reference_photos');

        // Prefill from existing draft if any
        $this->hydrateFromDraft($occurrence, $item);

        $this->open = true;
    }

    protected function rules(): array
    {
        return [
            'result' => [Rule::requiredIf(fn() => $this->method === 'boolean'), Rule::in(['Pass','Fail'])],
            'number' => [Rule::requiredIf(fn() => $this->method === 'number'), 'numeric',
                function (string $attr, $value, $fail) {
                    if ($value === null || $value === '') { return; }
                    if ($this->minValue !== null && $value < $this->minValue) {
                        $fail('下限値を下回っています。');
                    }
                    if ($this->maxValue !== null && $value > $this->maxValue) {
                        $fail('上限値を上回っています。');
                    }
                }
            ],
            'text'   => [Rule::requiredIf(fn() => $this->method === 'text'), 'string', 'max:2000'],
            'select' => [Rule::requiredIf(fn() => $this->method === 'select'), Rule::in($this->options)],
            'note'   => ['nullable', 'string', 'max:2000'],
            'attachments' => ['array','max:10'],
            'attachments.*' => ['nullable', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            'inspectorId' => ['required','integer','exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer','exists:users,id','different:inspectorId'],
        ];
    }

    protected function upsertDraftInspection(AssetGuardMaintenanceOccurrence $occurrence, AssetGuardInspectionChecklistItem $item): AssetGuardInspection
    {
        $inspection = AssetGuardInspection::query()->firstOrCreate([
            'asset_id' => $occurrence->asset_id,
            'checklist_id' => $item->checklist_id,
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

    public function saveDraft(): void
    {
        if ($this->occurrenceId === null || $this->checklistItemId === null) { return; }
        $this->validate();

        $occurrence = AssetGuardMaintenanceOccurrence::query()->findOrFail($this->occurrenceId);
        $item = AssetGuardInspectionChecklistItem::query()->findOrFail($this->checklistItemId);
        $inspection = $this->upsertDraftInspection($occurrence, $item);

        [$result, $value] = $this->buildOutcome();

        $record = AssetGuardInspectionItemResult::query()->updateOrCreate([
            'inspection_id' => $inspection->id,
            'checklist_item_id' => $item->id,
        ], [
            'result' => $result,
            'value' => $value,
            'note' => $this->note,
            'is_draft' => true,
        ]);

        foreach ($this->attachments as $file) {
            try {
                $record
                    ->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('attachments');
            } catch (\Throwable $e) {}
        }

        $this->dispatch('saved-draft');
    }

    public function finalize(): void
    {
        if ($this->occurrenceId === null || $this->checklistItemId === null) { return; }
        $this->validate();

        $occurrence = AssetGuardMaintenanceOccurrence::query()->findOrFail($this->occurrenceId);
        $item = AssetGuardInspectionChecklistItem::query()->findOrFail($this->checklistItemId);
        $inspection = $this->upsertDraftInspection($occurrence, $item);

        [$result, $value] = $this->buildOutcome();

        $record = AssetGuardInspectionItemResult::query()->updateOrCreate([
            'inspection_id' => $inspection->id,
            'checklist_item_id' => $item->id,
        ], [
            'result' => $result,
            'value' => $value,
            'note' => $this->note,
            'is_draft' => false,
        ]);

        foreach ($this->attachments as $file) {
            try {
                $record
                    ->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('attachments');
            } catch (\Throwable $e) {}
        }

        $inspection->update(['status' => 'Completed', 'performed_at' => Carbon::now()]);

        // Mark occurrence completed and create next based on item frequency
        $occurrence->update([
            'status' => 'Completed',
            'completed_at' => Carbon::now(),
        ]);

        $baseDate = Carbon::parse($occurrence->planned_at);
        $nextDue = ComputeInspectionSchedules::nextDueDate($item->frequency_unit, (int) $item->frequency_value, $baseDate);
        if ($nextDue) {
            AssetGuardMaintenanceOccurrence::query()->firstOrCreate([
                'maintenance_plan_id' => $occurrence->maintenance_plan_id,
                'asset_id' => $occurrence->asset_id,
                'planned_at' => $nextDue->toDateTimeString(),
            ], [
                'status' => 'Scheduled',
            ]);
        }

        $this->reset(['attachments']);
        $this->open = false;
        $this->dispatch('executed');
        $this->dispatch('refresh')->to(Index::class);
    }

    protected function buildOutcome(): array
    {
        if ($this->method === 'number') {
            $value = is_null($this->number) ? null : (float) $this->number;
            if ($value === null) {
                return ['Pass', null];
            }
            $pass = true;
            if ($this->minValue !== null) { $pass = $pass && $value >= $this->minValue; }
            if ($this->maxValue !== null) { $pass = $pass && $value <= $this->maxValue; }
            return [$pass ? 'Pass' : 'Fail', (string) $value];
        }

        return match ($this->method) {
            'boolean' => [$this->result ?? 'Pass', null],
            'text'    => ['Pass', $this->text],
            'select'  => ['Pass', $this->select],
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

    protected function hydrateFromDraft(AssetGuardMaintenanceOccurrence $occurrence, AssetGuardInspectionChecklistItem $item): void
    {
        // Load existing draft for same asset + checklist
        $draft = AssetGuardInspection::query()
            ->where('asset_id', $occurrence->asset_id)
            ->where('checklist_id', $item->checklist_id)
            ->where('status', 'Draft')
            ->latest('id')
            ->first();

        if (! $draft) {
            return;
        }

        $existing = AssetGuardInspectionItemResult::query()
            ->where('inspection_id', $draft->id)
            ->where('checklist_item_id', $item->id)
            ->first();

        if ($existing) {
            $this->note = $existing->note;
            if ($this->method === 'boolean') {
                $this->result = $existing->result ?: 'Pass';
            } elseif ($this->method === 'number') {
                $this->number = is_numeric($existing->value ?? null) ? (float) $existing->value : null;
            } elseif ($this->method === 'text') {
                $this->text = $existing->value;
            } elseif ($this->method === 'select') {
                $this->select = $existing->value;
            }
        }

        // Prefill inspectors from draft
        $this->inspectorId = $draft->performed_by_user_id ?: $this->inspectorId;
        $this->coInspectorIds = $draft->inspectors()->wherePivot('role', 'Assistant')->pluck('users.id')->all();
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.performer');
    }
}
