<?php

namespace Lastdino\AssetGuard\Services;

use Carbon\CarbonImmutable;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan as Plan;

class GenerateMaintenanceOccurrences
{
    public function __construct(public int $monthsAhead = 6) {}

    public function handle(Plan $plan): int
    {
        $count = 0;
        $start = CarbonImmutable::parse((string) $plan->start_date, $plan->timezone);
        $end = $plan->end_date
            ? CarbonImmutable::parse((string) $plan->end_date, $plan->timezone)
            : $start->addMonths($this->monthsAhead);

        // Use checklist-based frequency only; if missing, do nothing
        $unit = null;
        $mult = 1;
        if ($plan->checklist_id) {
            $cl = $plan->relationLoaded('checklist') ? $plan->checklist : $plan->checklist()->first();
            $unit = $cl?->frequency_unit;
            $mult = (int) ($cl?->frequency_value ?? 1);
        }

        if (! $unit) {
            return 0;
        }

        // Per-use plans should not pre-generate occurrences
        if ($unit === 'PerUse') {
            return 0;
        }

        $mult = max(1, $mult);

        if ($unit === 'OneTime') {
            $this->create($plan, $start);

            return 1;
        }

        // Determine step spec from unit and multiplier
        $stepSpec = match ($unit) {
            'Daily' => sprintf('%d day', $mult),
            'Weekly' => sprintf('%d week', $mult),
            'Monthly' => sprintf('%d month', $mult),
            'Quarterly' => sprintf('%d month', 3 * $mult),
            'SemiAnnual' => sprintf('%d month', 6 * $mult),
            'Annual' => sprintf('%d year', $mult),
            default => null,
        };

        if ($stepSpec === null) {
            return 0; // Custom: not implemented yet
        }

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->add($stepSpec)) {
            $this->create($plan, $cursor);
            $count++;
        }

        return $count;
    }

    protected function create(Plan $plan, CarbonImmutable $when): void
    {
        Occurrence::firstOrCreate([
            'maintenance_plan_id' => $plan->id,
            'asset_id' => $plan->asset_id,
            'planned_at' => $when->toDateTimeString(),
        ], [
            'status' => 'Scheduled',
            'assigned_to' => $plan->assigned_to,
        ]);
    }
}
