<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardIncident;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Livewire\Component;

class KpiCards extends Component
{
    public ?float $complianceRate = null;
    public int $overdueCount = 0;
    public int $openIncidents = 0;
    public int $downAssets = 0;

    // Severity-aware KPIs
    public array $openIncidentsBySeverity = ['Low'=>0,'Medium'=>0,'High'=>0,'Critical'=>0];
    public int $openIncidentsSlaBreached = 0;
    public array $mttrBySeverity = ['Low'=>null,'Medium'=>null,'High'=>null,'Critical'=>null];

    public string $period = 'this_month';

    public function mount(string $period = 'this_month'): void
    {
        $this->period = $period;
        $this->calculate();
    }

    protected function periodRange(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        if ($this->period === 'this_week') {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
        } elseif ($this->period === 'this_quarter') {
            $start = now()->firstOfQuarter();
            $end = now()->lastOfQuarter();
        }

        return [$start, $end];
    }

    private function slaHoursFor(string $severity): int
    {
        return match ($severity) {
            'Critical' => 4,
            'High' => 8,
            'Medium' => 24,
            'Low' => 72,
            default => 24,
        };
    }

    private function slaDueAtFor(\Illuminate\Support\Carbon $occurredAt, string $severity): \Illuminate\Support\Carbon
    {
        return $occurredAt->copy()->addHours($this->slaHoursFor($severity));
    }

    public function calculate(): void
    {
        [$start, $end] = $this->periodRange();

        $planned = Occurrence::query()
            ->whereBetween('planned_at', [$start, $end])
            ->count();

        $completed = Occurrence::query()
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        $this->complianceRate = $planned > 0 ? round($completed / $planned * 100, 1) : null;

        $this->overdueCount = Occurrence::query()
            ->where('planned_at', '<', now())
            ->whereNull('completed_at')
            ->count();

        $open = AssetGuardIncident::query()
            ->select(['severity', 'occurred_at'])
            ->whereIn('status', ['Waiting', 'InProgress'])
            ->get();

        $this->openIncidents = $open->count();
        $this->openIncidentsBySeverity = ['Low'=>0,'Medium'=>0,'High'=>0,'Critical'=>0];
        foreach ($open as $row) {
            $sev = $row->severity ?: 'Medium';
            if (isset($this->openIncidentsBySeverity[$sev])) {
                $this->openIncidentsBySeverity[$sev]++;
            }
        }

        $this->openIncidentsSlaBreached = $open->filter(function ($i) {
            if (!$i->occurred_at) { return false; }
            $due = $this->slaDueAtFor($i->occurred_at, $i->severity ?: 'Medium');
            return now()->greaterThan($due);
        })->count();

        $completedBySeverity = AssetGuardIncident::query()
            ->where('status', 'Completed')
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('occurred_at')
            ->get(['severity', 'occurred_at', 'completed_at']);

        $groups = $completedBySeverity->groupBy(fn($i) => $i->severity ?: 'Medium');
        $this->mttrBySeverity = ['Low'=>null,'Medium'=>null,'High'=>null,'Critical'=>null];
        foreach (['Low','Medium','High','Critical'] as $sev) {
            $set = $groups->get($sev, collect());
            if ($set->isNotEmpty()) {
                $avgSeconds = (int) round($set->avg(fn($i) => $i->completed_at->diffInSeconds($i->occurred_at)));
                $this->mttrBySeverity[$sev] = round($avgSeconds / 3600, 1);
            }
        }

        $this->downAssets = AssetGuardAsset::query()
            ->where('status', 'stopped')
            ->count();
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.dashboard.kpi-cards');
    }
}
