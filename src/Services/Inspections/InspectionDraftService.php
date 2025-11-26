<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services\Inspections;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\{AssetGuardInspection, AssetGuardInspectionItemResult, AssetGuardInspectionChecklist, AssetGuardInspectionChecklistItem, AssetGuardMaintenancePlan};

final class InspectionDraftService
{
    public function upsertDraft(int $assetId, int $checklistId, int $inspectorId, array $assistantIds = []): AssetGuardInspection
    {
        $inspection = AssetGuardInspection::query()->firstOrCreate([
            'asset_id' => $assetId,
            'checklist_id' => $checklistId,
            'status' => 'Draft',
        ], [
            'performed_by_user_id' => $inspectorId,
            'performed_at' => Carbon::now(),
        ]);

        $inspection->update(['performed_by_user_id' => $inspectorId]);
        $sync = collect($assistantIds)->unique()->values()->mapWithKeys(static fn($id) => [$id => ['role' => 'Assistant']])->all();
        $inspection->inspectors()->sync($sync + [
            $inspectorId => ['role' => 'Primary'],
        ]);

        return $inspection;
    }

    /**
     * Prefill a batch-like forms array from existing draft.
     * @param array<int, array<string, mixed>> $forms In/out parameter by reference
     */
    public function hydrateDraftBatch(int $assetId, int $checklistId, array &$forms, ?int &$inspectorId = null, ?array &$coInspectorIds = null): void
    {
        $draft = AssetGuardInspection::query()
            ->where('asset_id', $assetId)
            ->where('checklist_id', $checklistId)
            ->where('status', 'Draft')
            ->latest('id')
            ->first();
        if (!$draft) { return; }

        if ($inspectorId !== null) {
            $inspectorId = $draft->performed_by_user_id ?: $inspectorId;
        }
        if (is_array($coInspectorIds)) {
            $coInspectorIds = $draft->inspectors()->wherePivot('role', 'Assistant')->pluck('users.id')->all();
        }

        $existing = AssetGuardInspectionItemResult::query()->where('inspection_id', $draft->id)->get();
        foreach ($existing as $res) {
            if (!isset($forms[$res->checklist_item_id])) { continue; }
            $method = $forms[$res->checklist_item_id]['method'] ?? null;
            $forms[$res->checklist_item_id]['note'] = $res->note;
            if ($method === 'boolean') {
                $forms[$res->checklist_item_id]['result'] = $res->result ?: 'Pass';
            } elseif ($method === 'number') {
                $forms[$res->checklist_item_id]['number'] = is_numeric($res->value ?? null) ? (float) $res->value : null;
            } elseif ($method === 'text') {
                $forms[$res->checklist_item_id]['text'] = $res->value;
            } elseif ($method === 'select') {
                $forms[$res->checklist_item_id]['select'] = $res->value;
            }
        }
    }

    /**
     * Build initial forms from a checklist (ordered) for pre-use/ad-hoc.
     * @return array<int, array<string, mixed>>
     */
    public function buildFormsForChecklist(int $checklistId, ChecklistOptionsService $options): array
    {
        $forms = [];
        $checklist = AssetGuardInspectionChecklist::query()
            ->with(['items' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->findOrFail($checklistId);

        foreach ($checklist->items as $item) {
            $forms[$item->id] = [
                'name' => $item->name,
                'method' => $item->method,
                'result' => $item->method === 'boolean' ? 'Pass' : null,
                'number' => null,
                'text' => null,
                'select' => null,
                'note' => null,
                'min' => $item->min_value,
                'max' => $item->max_value,
                'options' => $options->extract($item),
                'media' => $item->getMedia('reference_photos')
                    ->map(static fn($m) => [
                        'id' => $m->id,
                        'file_name' => $m->file_name,
                    ])->all(),
            ];
        }
        return $forms;
    }

    /**
     * Build initial forms from a plan's checklist (ordered) for batch mode.
     * @return array<int, array<string, mixed>>
     */
    public function buildFormsForPlan(int $planId, ChecklistOptionsService $options, ?int &$assetId = null, ?int &$checklistId = null): array
    {
        $plan = AssetGuardMaintenancePlan::query()->with('checklist.items', 'asset')->findOrFail($planId);
        $forms = [];
        $items = $plan->checklist?->items ?? collect();
        foreach ($items as $item) {
            $forms[$item->id] = [
                'name' => $item->name,
                'method' => $item->method,
                'result' => $item->method === 'boolean' ? 'Pass' : null,
                'number' => null,
                'text' => null,
                'select' => null,
                'note' => null,
                'min' => $item->min_value,
                'max' => $item->max_value,
                'options' => $options->extract($item),
                'media' => $item->getMedia('reference_photos')
                    ->map(static fn($m) => [
                        'id' => $m->id,
                        'file_name' => $m->file_name,
                    ])->all(),
            ];
        }
        if ($assetId !== null) { $assetId = (int) $plan->asset_id; }
        if ($checklistId !== null) { $checklistId = (int) ($plan->checklist?->id); }
        return $forms;
    }
}
