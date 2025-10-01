<div class="grid gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="md">{{ __('asset-guard::inspections.heading_occurrences') }}</flux:heading>
    </div>

    <div class="grid gap-2">
        @forelse($this->dueOccurrences as $occ)
            <div class="rounded border p-3 dark:border-zinc-700" wire:key="occ-{{ $occ->id }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ $occ->asset->code }} — {{ $occ->asset->name }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ __('asset-guard::inspections.planned_for') }}: {{ \Illuminate\Support\Carbon::parse($occ->planned_at)->toDateString() }} ／ {{ __('asset-guard::inspections.plan') }}: {{ $occ->plan->title }} ／ {{ __('asset-guard::inspections.checklist') }}: {{ $occ->plan->checklist?->name }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button
                            size="xs"
                            variant="primary"
                            wire:click="$dispatch('open-batch-performer', [{{ $occ->id }}])"
                        >
                            {{ __('asset-guard::inspections.perform_batch') }}
                        </flux:button>

                        <flux:dropdown>
                            <flux:button size="xs" variant="subtle">{{ __('asset-guard::inspections.perform_detail') }}</flux:button>
                            @php
                                $items = optional($occ->plan?->checklist)->items ?? collect();
                            @endphp
                            @if($items->isEmpty())
                                <div class="text-xs text-zinc-500 px-2 py-1">{{ __('asset-guard::inspections.no_due_items') }}</div>
                            @else
                                <flux:menu>
                                    @foreach($items as $item)
                                        <flux:menu.item wire:click="$dispatch('open-performer', [{{ $occ->id }}, {{ $item->id }}])">
                                            <div>
                                                <div class="text-sm font-medium">{{ $item->name }}</div>
                                                <div class="text-[10px] text-zinc-500">{{ __('asset-guard::inspections.due_date') }}: {{ optional(\Illuminate\Support\Carbon::parse($occ->planned_at))->toDateString() }}</div>
                                            </div>
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                    @endforeach
                                </flux:menu>
                            @endif
                        </flux:dropdown>
                    </div>
                </div>
            </div>
        @empty
            <flux:callout>{{ __('asset-guard::inspections.empty_occurrences') }}</flux:callout>
        @endforelse
    </div>

    @livewire('asset-guard.inspections.performer')
    @livewire('asset-guard.inspections.batch-performer')
</div>
