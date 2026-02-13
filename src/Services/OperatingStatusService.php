<?php

namespace Lastdino\AssetGuard\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardOperatingLog;

class OperatingStatusService
{
    public function setStatus(AssetGuardAsset $asset, string $status, CarbonInterface|Carbon|null $at = null, string $source = 'manual'): void
    {
        $at = $at ?? Carbon::now();

        // 進行中の最新ログを取得
        $currentLog = $asset->currentOperatingLog;

        // すでに同じステータスなら何もしない
        if ($currentLog && $currentLog->status === $status) {
            return;
        }

        // 現在のログがあれば終了させる
        if ($currentLog) {
            if ($currentLog->started_at->toDateTimeString() >= $at->toDateTimeString()) {
                $currentLog->delete();
                $previousLog = $asset->operatingLogs()->latest('started_at')->first();
                if ($previousLog) {
                    $previousLog->update(['ended_at' => null]);
                    if ($previousLog->status === $status) {
                        return;
                    }
                }
            } else {
                $currentLog->update(['ended_at' => $at]);
            }
        }

        // 新しいログを作成
        $asset->operatingLogs()->create([
            'status' => $status,
            'started_at' => $at,
            'source' => $source,
        ]);
    }

    public function setStatusForDay(AssetGuardAsset $asset, CarbonInterface|Carbon $date, string $status, string $source = 'manual'): void
    {
        $startOfDay = Carbon::instance($date)->startOfDay();
        $endOfDay = Carbon::instance($date)->endOfDay();

        if ($status === 'running') {
            $this->clearLogsForRange($asset, $startOfDay, $endOfDay);

            $asset->operatingLogs()->create([
                'status' => 'running',
                'started_at' => $startOfDay,
                'ended_at' => $endOfDay,
                'source' => $source,
            ]);
        } else {
            $this->clearLogsForRange($asset, $startOfDay, $endOfDay);
        }
    }

    protected function clearLogsForRange(AssetGuardAsset $asset, CarbonInterface|Carbon $start, CarbonInterface|Carbon $end): void
    {
        $startStr = $start->toDateTimeString();
        $endStr = $end->toDateTimeString();

        $logs = AssetGuardOperatingLog::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'running')
            ->where(function ($q) use ($startStr, $endStr) {
                $q->where(function ($qq) use ($startStr, $endStr) {
                    $qq->where('started_at', '>=', $startStr)
                        ->where('started_at', '<=', $endStr);
                })
                    ->orWhere(function ($qq) use ($startStr, $endStr) {
                        $qq->whereNotNull('ended_at')
                            ->where('ended_at', '>=', $startStr)
                            ->where('ended_at', '<=', $endStr);
                    })
                    ->orWhere(function ($qq) use ($startStr, $endStr) {
                        $qq->where('started_at', '<', $startStr)
                            ->where(function ($qqq) use ($endStr) {
                                $qqq->whereNull('ended_at')
                                    ->orWhere('ended_at', '>', $endStr);
                            });
                    });
            })
            ->get();

        foreach ($logs as $log) {
            $logStart = $log->started_at->toDateTimeString();
            $logEnd = $log->ended_at ? $log->ended_at->toDateTimeString() : null;

            if ($logStart >= $startStr && ($logEnd !== null && $logEnd <= $endStr)) {
                $log->delete();
            } elseif ($logStart < $startStr && ($logEnd === null || $logEnd > $endStr)) {
                $oldEnd = $log->ended_at;
                $log->update(['ended_at' => $start]);

                $asset->operatingLogs()->create([
                    'status' => 'running',
                    'started_at' => $end,
                    'ended_at' => $oldEnd,
                    'source' => $log->source,
                ]);
            } elseif ($logStart < $startStr) {
                $log->update(['ended_at' => $start]);
            } else {
                $log->update(['started_at' => $end]);
            }
        }
    }

    public function toggleStatus(AssetGuardAsset $asset, CarbonInterface|Carbon|null $at = null): void
    {
        $currentStatus = $asset->operating_status;
        $newStatus = $currentStatus === 'running' ? 'stopped' : 'running';

        $this->setStatus($asset, $newStatus, $at);
    }

    public function getStatusForDate(AssetGuardAsset $asset, CarbonInterface|Carbon $date): string
    {
        $startOfDay = Carbon::instance($date)->startOfDay();
        $endOfDay = Carbon::instance($date)->endOfDay();

        // 比較のために文字列に変換（DBの精度に合わせる）
        $startStr = $startOfDay->toDateTimeString();
        $endStr = $endOfDay->toDateTimeString();

        // その日に少しでも 'running' であったかどうか
        $hasRun = AssetGuardOperatingLog::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'running')
            ->where(function ($q) use ($startStr, $endStr) {
                // 開始が期間内
                $q->where(function ($qq) use ($startStr, $endStr) {
                    $qq->where('started_at', '>=', $startStr)
                        ->where('started_at', '<', $endStr); // 23:59:59 は翌日の 00:00:00 と重ならないように <
                })
                // 終了が期間内
                    ->orWhere(function ($qq) use ($startStr, $endStr) {
                        $qq->whereNotNull('ended_at')
                            ->where('ended_at', '>', $startStr) // 00:00:00 は前日の終わりなので >
                            ->where('ended_at', '<=', $endStr);
                    })
                // 期間を完全に跨いでいる
                    ->orWhere(function ($qq) use ($startStr, $endStr) {
                        $qq->where('started_at', '<', $startStr)
                            ->where(function ($qqq) use ($endStr) {
                                $qqq->whereNull('ended_at')
                                    ->orWhere('ended_at', '>', $endStr);
                            });
                    });
            })
            ->exists();

        return $hasRun ? 'running' : 'stopped';
    }
}
