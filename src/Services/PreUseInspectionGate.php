<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist as Checklist;
use Lastdino\AssetGuard\Models\AssetGuardAsset as Asset;

class PreUseInspectionGate
{
    public function __construct(public int $assetId) { }

    public function isInspectionRequired(): bool
    {
        $assetTypeId = Asset::query()->whereKey($this->assetId)->value('asset_type_id');

        $checklists = Checklist::query()
            ->where('require_before_activation', true)
            ->where('active', true)
            ->where(function ($q) use ($assetTypeId) {
                $q->where(function ($q) use ($assetTypeId) {
                    $q->where('applies_to', 'asset_type')
                      ->when($assetTypeId, fn ($q) => $q->where('asset_type_id', $assetTypeId));
                })->orWhere(function ($q) {
                    $q->where('applies_to', 'asset');
                });
            })
            ->get(['id']);

        if ($checklists->isEmpty()) {
            // 使用前必須チェックリスト自体が無ければ、トリガー不要
            return false;
        }

        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        foreach ($checklists as $checklist) {
            $lastCompleted = AssetGuardInspection::query()
                ->where('asset_id', $this->assetId)
                ->where('checklist_id', $checklist->id)
                ->where('status', 'Completed')
                ->latest('performed_at')
                ->first();

            if (! $lastCompleted) {
                return true; // 1件も完了がなければ要点検
            }

            $performed = $lastCompleted->performed_at?->copy()->timezone($tz);
            if ($performed === null || ! $performed->isSameDay($now)) {
                return true; // 当日未完了なら要点検
            }
        }

        return false; // 全て当日完了済み
    }
}
