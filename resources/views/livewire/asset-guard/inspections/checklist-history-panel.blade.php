<div class="grid gap-4">
    <flux:heading>点検履歴</flux:heading>
    <div class="space-y-6">
        @forelse ($this->checklists as $checklist)
            <div class="rounded border p-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $checklist->name }}</flux:heading>
                </div>

                @livewire(\Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistHistoryList::class, [
                    'checklistId' => $checklist->id,
                ], key('hist-'.$checklist->id))
            </div>
        @empty
            <p class="text-gray-500">チェックリストがありません。</p>
        @endforelse
    </div>
</div>
