<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-zinc-500">
            <th class="py-2">{{ __('asset-guard::common.asset') }}</th>
            <th class="py-2">{{ __('asset-guard::common.checklist') }}</th>
            <th class="py-2">{{ __('asset-guard::common.due_date') }}</th>
            <th class="py-2">{{ __('asset-guard::common.assignee') }}</th>
            <th class="py-2">{{ __('asset-guard::common.status') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr
                class="border-t cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800"
                wire:click="$dispatch('open-batch-performer', [{{ $row->id }}])"
            >
                <td class="py-2">{{ optional($row->asset)->name ?? '-' }}</td>
                <td class="py-2">{{ optional(optional($row->plan)->checklist)->name ?? '-' }}</td>
                <td class="py-2">{{ optional($row->planned_at)?->format('Y-m-d') }}</td>
                <td class="py-2">{{ optional(optional($row->plan)->assignee)->name ?? '-' }}</td>
                <td class="py-2">{{ isset($row->planned_at) && $row->planned_at < now() ? __('asset-guard::common.overdue') : __('asset-guard::common.upcoming') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-4 text-center text-zinc-500">{{ __('asset-guard::common.no_data') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="mt-2">
        {{ $rows->links() }}
    </div>
</div>
