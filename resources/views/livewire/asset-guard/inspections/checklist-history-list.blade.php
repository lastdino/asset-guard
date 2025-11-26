<div class="mt-3">
    <div class="divide-y">
        @forelse ($inspections as $inspection)
            <div class="py-3 flex items-start gap-3" wire:key="inspection-{{ $inspection->id }}">
                <div class="grow">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="font-medium">実施日:</span>
                        <span>{{ optional($inspection->performed_at)->format('Y-m-d H:i') }}</span>
                        @if($inspection->performer)
                            <span class="text-gray-500">/</span>
                            <span class="font-medium">担当:</span>
                            <span>{{ $inspection->performer->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0">
                    <flux:button variant="subtle" wire:click="$dispatch('open-inspection-detail', { id: {{ $inspection->id }} })">詳細</flux:button>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 py-2">履歴がありません。</p>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $inspections->links() }}
    </div>
</div>
