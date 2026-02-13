<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Services\PreUseInspectionGate;
use Livewire\Component;
use Livewire\WithPagination;

class RunningUninspectedAssets extends Component
{
    use WithPagination;

    public function render()
    {
        // 当日一度でも稼働実績のある資産を取得
        $allAssets = AssetGuardAsset::query()
            ->with(['location', 'assetType'])
            ->get();

        // その中で、当日の点検が必要なものをフィルタリング
        $uninspectedAssets = $allAssets->filter(function ($asset) {
            return (new PreUseInspectionGate(assetId: $asset->id))->isInspectionRequired();
        });

        // ページネーションが必要な場合は手動で行うか、クエリで完結させる必要がある。
        // PreUseInspectionGate のロジックをクエリ化するのは複雑なので、一旦コレクションで処理する。
        // 大量にある場合はパフォーマンスに影響するが、ダッシュボードのアラート用なので数十件程度を想定。

        return view('asset-guard::livewire.asset-guard.dashboard.running-uninspected-assets', [
            'assets' => $uninspectedAssets,
        ]);
    }
}
