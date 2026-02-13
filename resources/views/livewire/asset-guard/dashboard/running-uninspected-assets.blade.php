<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-zinc-500">
            <th class="py-2">{{ __('asset-guard::common.asset_code') }}</th>
            <th class="py-2">{{ __('asset-guard::common.asset_name') }}</th>
            <th class="py-2">{{ __('asset-guard::common.location') }}</th>
            <th class="py-2">{{ __('asset-guard::common.asset_type') }}</th>
            <th class="py-2">{{ __('asset-guard::common.action') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($assets as $asset)
            <tr class="border-t hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <td class="py-2">{{ $asset->code }}</td>
                <td class="py-2">{{ $asset->name }}</td>
                <td class="py-2">{{ optional($asset->location)->name ?? '-' }}</td>
                <td class="py-2">{{ optional($asset->assetType)->name ?? '-' }}</td>
                <td class="py-2">
                    <flux:button
                        variant="primary"
                        size="sm"
                        wire:click="$dispatch('open-inspection', [{ mode: 'preuse', assetId: {{ $asset->id }} }])"
                    >
                        {{ __('asset-guard::common.inspect') }}
                    </flux:button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-4 text-center text-zinc-500">{{ __('asset-guard::common.no_running_uninspected_assets') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
