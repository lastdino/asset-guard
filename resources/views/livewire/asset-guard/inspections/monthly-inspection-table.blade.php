<div class="p-6">
    <div class="flex items-center justify-between mb-6 print:hidden">
        <div>
            <flux:heading size="xl">{{ $this->asset->name }} ({{ $this->asset->code }})</flux:heading>
            <div class="flex items-center gap-2 mt-1">
                <flux:text variant="subtle">月次点検記録表</flux:text>
                <flux:button variant="subtle" size="sm" :href="route('asset-guard.assets.monthly-inspections', ['assetId' => $assetId])" icon="arrow-path">最新月</flux:button>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <flux:button variant="subtle" wire:click="previousMonth" icon="chevron-left" />
            <flux:heading size="lg">{{ \Illuminate\Support\Carbon::parse($this->yearMonth)->format('Y年n月') }}</flux:heading>
            <flux:button variant="subtle" wire:click="nextMonth" icon="chevron-right" />
            <flux:button variant="primary" icon="printer" onclick="window.print()">印刷</flux:button>
        </div>
    </div>

    <div class="hidden print:block mb-6">
        <flux:heading size="xl" class="text-center">{{ $this->asset->name }} ({{ $this->asset->code }}) 点検記録表</flux:heading>
        <flux:heading size="lg" class="text-center mt-2">{{ \Illuminate\Support\Carbon::parse($this->yearMonth)->format('Y年n月') }}</flux:heading>
    </div>

    <div class="overflow-x-auto border rounded-lg print:overflow-visible print:border-none">
        <style>
            @media print {
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .print\:hidden {
                    display: none !important;
                }
                table {
                    width: 100% !important;
                    table-layout: fixed !important;
                    border-collapse: collapse !important;
                    font-size: 8pt !important;
                }
                th, td {
                    border: 1px solid #000 !important;
                    padding: 2px !important;
                    word-wrap: break-word !important;
                }
                .sticky {
                    position: static !important;
                }
                .bg-zinc-50, .bg-zinc-900, .bg-white, .bg-zinc-950 {
                    background-color: transparent !important;
                }
                .bg-green-50 { background-color: #f0fdf4 !important; }
                .bg-red-50 { background-color: #fef2f2 !important; }
                .bg-blue-50\/50 { background-color: #eff6ff !important; }
                .bg-red-50\/50 { background-color: #fef2f2 !important; }
                .ring-red-500 {
                    box-shadow: inset 0 0 0 2px #ef4444 !important;
                }
            }
        </style>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800 print:min-w-0">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th scope="col" class="sticky left-0 z-10 bg-zinc-50 dark:bg-zinc-900 py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-zinc-900 dark:text-zinc-100 sm:pl-6 border-r print:static print:w-40">
                        点検項目
                    </th>
                    @for ($day = 1; $day <= $this->daysInMonth; $day++)
                        @php
                            $date = \Illuminate\Support\Carbon::parse($this->yearMonth)->day($day);
                            $isSaturday = $date->isSaturday();
                            $isSunday = $date->isSunday();
                            $dayColorClass = match(true) {
                                $isSaturday => 'bg-blue-50/50 dark:bg-blue-900/20',
                                $isSunday => 'bg-red-50/50 dark:bg-red-900/20',
                                default => ''
                            };
                        @endphp
                        <th scope="col" class="px-2 py-3.5 text-center text-xs font-semibold text-zinc-900 dark:text-zinc-100 min-w-[3rem] border-r print:min-w-0 print:px-0 {{ $dayColorClass }}">
                            {{ $day }}
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-950">
                @foreach ($this->checklistsWithItems as $checklist)
                    <tr class="bg-zinc-50 dark:bg-zinc-900">
                        <td colspan="{{ $this->daysInMonth + 1 }}" class="py-2 pl-4 pr-3 text-sm font-bold text-zinc-900 dark:text-zinc-100 sm:pl-6 border-r">
                            {{ $checklist->name }}
                        </td>
                    </tr>
                    @foreach ($checklist->items as $item)
                        <tr wire:key="item-{{ $item->id }}" class="print:break-inside-avoid">
                            <td class="sticky left-0 z-10 bg-white dark:bg-zinc-950 py-4 pl-4 pr-3 text-sm font-medium text-zinc-900 dark:text-zinc-100 sm:pl-6 border-r print:static print:py-1">
                                {{ $item->name }}
                            </td>
                            @for ($day = 1; $day <= $this->daysInMonth; $day++)
                                @php
                                    $date = \Illuminate\Support\Carbon::parse($this->yearMonth)->day($day);
                                    $isSaturday = $date->isSaturday();
                                    $isSunday = $date->isSunday();
                                    $result = $this->results[$item->id][$day] ?? null;
                                    $isScheduled = $this->schedules[$checklist->id][$day] ?? false;
                                    $colorClass = match($result['result'] ?? '') {
                                        'pass' => 'bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-300',
                                        'fail' => 'bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300',
                                        'na' => 'bg-zinc-50 dark:bg-zinc-800 text-zinc-500',
                                        default => match(true) {
                                            $isSaturday => 'bg-blue-50/50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-800/30 cursor-pointer',
                                            $isSunday => 'bg-red-50/50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-800/30 cursor-pointer',
                                            default => 'hover:bg-zinc-50 dark:hover:bg-zinc-900 cursor-pointer'
                                        }
                                    };
                                    $borderClass = $isScheduled ? 'ring-2 ring-inset ring-red-500' : 'border-r';
                                @endphp
                                <td
                                    wire:click="openEntryModal({{ $item->id }}, {{ $day }})"
                                    class="px-2 py-4 text-center text-sm cursor-pointer {{ $colorClass }} {{ $borderClass }} print:py-1 print:px-0"
                                    @if($isScheduled) title="保守予定日" @endif
                                >
                                    @if ($result)
                                        <div class="flex flex-col items-center gap-1 print:gap-0">
                                            @if ($item->method === 'number')
                                                <span class="font-medium">{{ $result['value'] }}</span>
                                            @else
                                                @if ($result['result'] === 'pass')
                                                    <flux:icon icon="check" variant="micro" class="mx-auto print:h-3 print:w-3" />
                                                @elseif ($result['result'] === 'fail')
                                                    <flux:icon icon="x-mark" variant="micro" class="mx-auto print:h-3 print:w-3" />
                                                @else
                                                    -
                                                @endif
                                            @endif
                                            <span class="text-[10px] text-zinc-500 leading-tight truncate max-w-full print:text-[6pt]" title="{{ $result['performer'] }}">
                                                {{ $result['performer'] }}
                                            </span>
                                        </div>
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    @livewire('asset-guard.inspections.performer-unified')
</div>
