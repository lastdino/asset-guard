<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardAsset as Asset;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist as Checklist;

class PreUseInspectionGate
{
    public function __construct(public int $assetId) {}

    public function isInspectionRequired(): bool
    {
        $asset = Asset::query()->find($this->assetId);
        if (! $asset) {
            return false;
        }

        // 停止中なら点検不要（将来的に稼働ログベースで判断）
        if ($asset->operating_status !== 'running') {
            return false;
        }

        $assetTypeId = $asset->asset_type_id;

        $checklists = Checklist::query()
            ->where('require_before_activation', true)
            ->where('active', true)
            ->where(function ($q) use ($assetTypeId) {
                $q->where(function ($q) use ($assetTypeId) {
                    $q->where('applies_to', 'asset_type')
                        ->when($assetTypeId, fn ($q) => $q->where('asset_type_id', $assetTypeId));
                })->orWhere(function ($q) {
                    $q->where('applies_to', 'asset')
                        ->where('asset_id', $this->assetId);
                });
            })
            ->get(['id']);

        if ($checklists->isEmpty()) {
            // 使用前必須チェックリスト自体が無ければ、トリガー不要
            return false;
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();

        foreach ($checklists as $checklist) {
            $isDone = AssetGuardInspection::query()
                ->where('asset_id', $this->assetId)
                ->where('checklist_id', $checklist->id)
                ->where('status', 'Completed')
                ->whereDate('performed_at', $today)
                ->exists();

            if (! $isDone) {
                return true; // 当日完了していなければ要点検
            }
        }

        return false; // 全て当日完了済み
    }
}
