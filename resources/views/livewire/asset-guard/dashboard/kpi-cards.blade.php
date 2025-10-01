<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="rounded border p-3 bg-white dark:bg-zinc-900">
        <div class="text-xs text-zinc-500">コンプライアンス率</div>
        <div class="text-2xl font-semibold mt-1">{{ $complianceRate !== null ? $complianceRate.'%' : '-' }}</div>
    </div>

    <div class="rounded border p-3 bg-white dark:bg-zinc-900">
        <div class="text-xs text-zinc-500">期限超過点検</div>
        <div class="text-2xl font-semibold mt-1">{{ $overdueCount }}</div>
    </div>

    <div class="rounded border p-3 bg-white dark:bg-zinc-900">
        <div class="text-xs text-zinc-500">未クローズ故障（総数 / SLA超過）</div>
        <div class="text-2xl font-semibold mt-1">{{ $openIncidents }}<span class="text-sm text-zinc-500"> / {{ $openIncidentsSlaBreached }}</span></div>
        <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 flex gap-2 flex-wrap">
            <span>Lo: {{ $openIncidentsBySeverity['Low'] }}</span>
            <span>Md: {{ $openIncidentsBySeverity['Medium'] }}</span>
            <span>Hi: {{ $openIncidentsBySeverity['High'] }}</span>
            <span>Cr: {{ $openIncidentsBySeverity['Critical'] }}</span>
        </div>
    </div>

    <div class="rounded border p-3 bg-white dark:bg-zinc-900">
        <div class="text-xs text-zinc-500">MTTR（時間, 期間内平均）</div>
        <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 grid grid-cols-2 gap-x-2">
            <div>Lo: {{ $mttrBySeverity['Low'] ?? '-' }}</div>
            <div>Md: {{ $mttrBySeverity['Medium'] ?? '-' }}</div>
            <div>Hi: {{ $mttrBySeverity['High'] ?? '-' }}</div>
            <div>Cr: {{ $mttrBySeverity['Critical'] ?? '-' }}</div>
        </div>
    </div>
</div>
