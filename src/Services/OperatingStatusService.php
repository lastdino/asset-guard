<?php

namespace Lastdino\AssetGuard\Services;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardOperatingLog;

class OperatingStatusService
{
    public function setStatus(AssetGuardAsset $asset, string $status, ?Carbon $at = null, string $source = 'manual'): void
    {
        $at = $at ?? Carbon::now();

        // 進行中の最新ログを取得
        $currentLog = $asset->currentOperatingLog;

        // すでに同じステータスなら何もしない（ソースが違っても状態が同じなら維持）
        if ($currentLog && $currentLog->status === $status) {
            return;
        }

        // 現在のログがあれば終了させる
        if ($currentLog) {
            $currentLog->update(['ended_at' => $at]);
        }

        // 新しいログを作成
        $asset->operatingLogs()->create([
            'status' => $status,
            'started_at' => $at,
            'source' => $source,
        ]);
    }

    public function toggleStatus(AssetGuardAsset $asset, ?Carbon $at = null): void
    {
        $currentStatus = $asset->operating_status;
        $newStatus = $currentStatus === 'running' ? 'stopped' : 'running';

        $this->setStatus($asset, $newStatus, $at);
    }

    public function getStatusForDate(AssetGuardAsset $asset, Carbon $date): string
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // その日に少しでも 'running' であったかどうか
        $hasRun = AssetGuardOperatingLog::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'running')
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('started_at', [$startOfDay, $endOfDay])
                    ->orWhere(function ($q2) use ($startOfDay, $endOfDay) {
                        $q2->where('started_at', '<=', $startOfDay)
                            ->where(function ($q3) use ($endOfDay) {
                                $q3->whereNull('ended_at')
                                    ->orWhere('ended_at', '>=', $endOfDay);
                            });
                    });
            })
            ->exists();

        return $hasRun ? 'running' : 'stopped';
    }
}
