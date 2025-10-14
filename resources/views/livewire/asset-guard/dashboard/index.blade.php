<div class="p-4 space-y-4">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-lg font-semibold">AssetGuard ダッシュボード</h1>

        <div class="flex items-center gap-2">
            <div>
                <flux:navbar>
                    <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}">ダッシュボード</flux:navbar.item>
                    <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.inspections.quick') }}">{{ __('asset-guard::quick_inspection.title') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.assets.index') }}">設備一覧</flux:navbar.item>
                    <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.incidents.index') }}">故障履歴一覧</flux:navbar.item>
                    <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.maintenance-plans.index') }}">保全計画</flux:navbar.item>

                    <flux:dropdown>
                        <flux:navbar.item class="max-lg:hidden" square icon="cog-6-tooth" href="#" label="Settings" />
                        <flux:menu>
                            <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.locations.index') }}">設置場所設定</flux:navbar.item>
                            <flux:menu.separator />
                            <flux:navbar.item href="{{ route(config('asset-guard.routes.prefix').'.asset-types.index') }}">資産タイプ設定</flux:navbar.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:navbar>
            </div>
            <select wire:model.live="period" class="border rounded px-2 py-1 text-sm bg-white dark:bg-zinc-900">
                <option value="this_week">今週</option>
                <option value="this_month">今月</option>
                <option value="this_quarter">四半期</option>
            </select>
        </div>
    </div>

    <div>
        @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Dashboard\\KpiCards', ['period' => $period], key('kpis-'.$period))
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 space-y-3">
            <div class="rounded border p-3 bg-white dark:bg-zinc-900">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-medium">期限間近/超過 点検</div>
                    <flux:button variant="ghost" wire:click="$refresh">更新</flux:button>
                </div>
                @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Dashboard\\OverdueInspections')
            </div>

            <div class="rounded border p-3 bg-white dark:bg-zinc-900">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-medium">インシデント（未クローズ）</div>
                    <flux:button variant="ghost" wire:click="$refresh">更新</flux:button>
                </div>
                @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Dashboard\\IncidentsTable')
            </div>
        </div>

        <div class="space-y-3">
            <div class="rounded border p-3 bg-white/50 dark:bg-zinc-900/50">
                <div class="font-medium mb-2">最近の活動</div>
                <div class="text-sm text-zinc-500">MVPのため、近日実装</div>
            </div>
        </div>
    </div>

    {{-- Inspection Batch Performer Modal (kept hidden until opened) --}}
    @livewire('asset-guard.inspections.batch-performer')
</div>
