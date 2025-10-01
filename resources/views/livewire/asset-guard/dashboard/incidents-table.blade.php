<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-zinc-500">
            <th class="py-2">資産</th>
            <th class="py-2">重大度</th>
            <th class="py-2">起票日時</th>
            <th class="py-2">経過</th>
            <th class="py-2">SLA</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr class="border-t">
                <td class="py-2">{{ optional($row->asset)->name ?? '-' }}</td>
                <td class="py-2">{{ $row->severity ?? '-' }}</td>
                <td class="py-2">{{ optional($row->created_at)?->format('Y-m-d H:i') }}</td>
                <td class="py-2">{{ optional($row->created_at)?->diffForHumans() }}</td>
                <td class="py-2">
                    @php
                        $occur = $row->occurred_at ?? $row->created_at;
                        $severity = $row->severity ?? 'Medium';
                        $due = $occur?->copy()->addHours(match($severity){'Critical'=>4,'High'=>8,'Medium'=>24,'Low'=>72,default=>24});
                    @endphp
                    @if($occur && $due)
                        @if(now()->greaterThan($due))
                            <span class="text-red-600">SLA超過 {{ $due->diffForHumans(null, true) }} 経過</span>
                        @else
                            <span class="text-zinc-600">SLA残り {{ now()->diffForHumans($due, true) }}</span>
                        @endif
                    @else
                        -
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-4 text-center text-zinc-500">データがありません</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="mt-2">
        {{ $rows->links() }}
    </div>
</div>
