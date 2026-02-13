<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Livewire\Component;

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
            'inspectorId' => ['required', 'integer', 'exists:users,id'],
            'coInspectorIds' => ['array'],
            'coInspectorIds.*' => ['integer', 'exists:users,id', 'different:inspectorId'],
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
            $this->message = __('asset-guard::quick_inspection.not_found');

            return;
        }

        $asset = AssetGuardAsset::query()->where('code', $code)->first();
        if ($asset === null) {
            $this->message = __('asset-guard::quick_inspection.not_found');

            return;
        }

        $this->foundAssetId = $asset->id;

        // Prefer an existing, not yet completed plan (do not create new)
        $plan = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'Scheduled')
            ->orderBy('scheduled_at')
            ->first();
        $this->pendingOccurrenceId = $plan?->id;

        // Resolve usable checklists (pre-use prioritized)
        $allCls = AssetGuardInspectionChecklist::query()
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
            ->get(['id', 'name', 'require_before_activation']);

        // Exclude pre-use checklists already completed today for this asset
        $today = now(config('app.timezone'))->toDateString();
        $preUseIds = $allCls->where('require_before_activation', true)->pluck('id');
        $donePreUseIds = collect();
        if ($preUseIds->isNotEmpty()) {
            $donePreUseIds = AssetGuardInspection::query()
                ->where('asset_id', $asset->id)
                ->whereIn('checklist_id', $preUseIds)
                ->where('status', 'Completed')
                ->whereDate('performed_at', $today)
                ->pluck('checklist_id');
        }

        // Build map of the nearest future scheduled date per NON pre-use checklist
        $now = now();
        $futurePlans = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'Scheduled')
            ->where('scheduled_at', '>=', $now)
            ->orderBy('scheduled_at')
            ->get(['checklist_id', 'scheduled_at', 'timezone']);

        $nearestByChecklist = [];
        foreach ($futurePlans as $fp) {
            $cid = (int) ($fp->checklist_id ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (! array_key_exists($cid, $nearestByChecklist)) {
                // Keep the first (earliest) one thanks to orderBy
                $tz = $fp->timezone ?: config('app.timezone');
                $nearestByChecklist[$cid] = [
                    'scheduled_at' => $fp->scheduled_at,
                    'timezone' => $tz,
                ];
            }
        }

        // Build a set of checklists that have past-due scheduled occurrences (no future one)
        $pastDueChecklistIds = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'Scheduled')
            ->where('scheduled_at', '<', $now)
            ->pluck('checklist_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $cls = $allCls
            ->reject(function ($c) use ($donePreUseIds) {
                return (bool) ($c->require_before_activation ?? false) && $donePreUseIds->contains($c->id);
            })
            // 教示対象の条件: 使用前点検以外は「プランがあること」（未来の予定 or 期限超過の過去予定）
            ->filter(function ($cl) use ($nearestByChecklist, $pastDueChecklistIds) {
                $isPreUse = (bool) ($cl->require_before_activation ?? false);
                if ($isPreUse) {
                    return true; // 使用前点検は常に候補
                }
                $cid = (int) $cl->id;
                $hasFuture = isset($nearestByChecklist[$cid]);
                $hasPastDue = in_array($cid, $pastDueChecklistIds, true);

                return $hasFuture || $hasPastDue; // どちらにも該当しない＝プラン無し→除外
            })
            ->map(function ($cl) use ($nearestByChecklist, $pastDueChecklistIds) {
                $isPreUse = (bool) $cl->require_before_activation;
                $scheduledDate = null;
                $overdue = false;
                if (! $isPreUse) {
                    $cid = (int) $cl->id;
                    if (isset($nearestByChecklist[$cid])) {
                        $info = $nearestByChecklist[$cid];
                        $scheduled = \Illuminate\Support\Carbon::parse($info['scheduled_at'])
                            ->timezone($info['timezone'] ?? config('app.timezone'))
                            ->translatedFormat('Y-m-d'); // date only per requirement
                        $scheduledDate = (string) $scheduled;
                    } else {
                        // No future schedule; mark overdue if there is any past-due scheduled occurrence
                        $overdue = in_array($cid, $pastDueChecklistIds, true);
                    }
                }

                return [
                    'id' => (int) $cl->id,
                    'name' => (string) $cl->name,
                    'pre_use' => $isPreUse,
                    // For non pre-use only: nearest future scheduled date (date-only). Null if none.
                    'scheduled_date' => $scheduledDate,
                    // Overdue flag for non pre-use when no future schedule exists but there are past-due ones
                    'overdue' => $overdue,
                ];
            })
            ->values()
            ->all();

        $this->availableChecklists = $cls;
        $this->selectedChecklistId = $cls[0]['id'] ?? null;

        // Open wizard modal
        $this->showStartModal = true;
    }

    public function startSelectedInspection(): void
    {
        if (! $this->foundAssetId || ! $this->inspectorId) {
            return;
        }

        // 既存の未完了予定を優先して開く機能は廃止。
        // 常にユーザーが選択したチェックリスト（使用前/アドホック）で開始する。

        if (! $this->selectedChecklistId) {
            $this->message = __('asset-guard::quick_inspection.no_plan');

            return;
        }

        // Determine mode by checklist type
        $mode = 'plan-batch';
        // Try to resolve from loaded availableChecklists first (avoid an extra query)
        $selected = collect($this->availableChecklists)
            ->firstWhere('id', (int) $this->selectedChecklistId);
        if (is_array($selected)) {
            $mode = ! empty($selected['pre_use']) ? 'preuse' : 'plan-batch';
        } else {
            // Fallback: query the checklist to check require_before_activation
            $cl = AssetGuardInspectionChecklist::query()->find($this->selectedChecklistId);
            if ($cl) {
                $mode = $cl->require_before_activation ? 'preuse' : 'plan-batch';
            }
        }

        if ($mode === 'plan-batch') {
            // ユーザーが選択したチェックリストに紐づく「予定（未実施）」を解決して実施する
            // 優先順: 直近の未来予定 -> 直近の期限超過（過去）予定
            $selectedChecklistId = (int) $this->selectedChecklistId;
            $assetId = (int) $this->foundAssetId;

            $now = now();

            $future = AssetGuardMaintenancePlan::query()
                ->where('asset_id', $assetId)
                ->where('status', 'Scheduled')
                ->where('checklist_id', $selectedChecklistId)
                ->where('scheduled_at', '>=', $now)
                ->orderBy('scheduled_at')
                ->first();

            $planId = $future?->id;

            if ($planId === null) {
                $pastDue = AssetGuardMaintenancePlan::query()
                    ->where('asset_id', $assetId)
                    ->where('status', 'Scheduled')
                    ->where('checklist_id', $selectedChecklistId)
                    ->where('scheduled_at', '<', $now)
                    ->orderByDesc('scheduled_at')
                    ->first();

                $planId = $pastDue?->id;
            }

            if ($planId === null) {
                // 対象チェックリストに紐づく予定が存在しないため開始できない
                $this->message = __('asset-guard::quick_inspection.no_plan');

                return;
            }

            $this->dispatch('open-inspection', [
                'mode' => 'plan-batch',
                'planId' => $planId,
                'inspectorId' => $this->inspectorId,
                'coInspectorIds' => $this->coInspectorIds,
            ]);
        } else {
            // Dispatch unified event (no occurrence creation for both modes)
            $this->dispatch('open-inspection', [
                'mode' => $mode,
                'assetId' => $this->foundAssetId,
                'checklistId' => $this->selectedChecklistId,
                'inspectorId' => $this->inspectorId,
                'coInspectorIds' => $this->coInspectorIds,
            ]);
        }
        $this->showStartModal = false;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.quick');
    }
}
