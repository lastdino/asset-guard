<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Small date utility to compute the next due date for inspections.
 *
 * This class contains no side effects and is safe to call from Livewire or Jobs.
 */
class InspectionScheduleCalculator
{
    /**
     * Compute a single next due date based on a unit, multiplier, and base date.
     *
     * Rules:
     * - null/empty unit → null
     * - OneTime/PerUse → null (do not auto-schedule next)
     * - Multiplier is coerced to >= 1
     * - Supported units include domain-specific values (Daily, Weekly, Monthly, Quarterly, SemiAnnual, Annual)
     *   and generic time units (minute(s), hour(s), day(s), week(s), month(s), year(s)).
     */
    public static function nextDueDate(?string $unit, int $multiplier, CarbonInterface $base): ?CarbonImmutable
    {
        if ($unit === null || $unit === '') {
            return null;
        }

        if ($unit === 'OneTime' || $unit === 'PerUse') {
            return null;
        }

        $multiplier = max(1, $multiplier);
        $base = CarbonImmutable::instance($base);

        // Domain-specific units first (title-cased variants used in this package)
        $next = match ($unit) {
            'Daily' => $base->addDays($multiplier),
            'Weekly' => $base->addWeeks($multiplier),
            'Monthly' => $base->addMonthsNoOverflow($multiplier),
            'Quarterly' => $base->addMonthsNoOverflow(3 * $multiplier),
            'SemiAnnual' => $base->addMonthsNoOverflow(6 * $multiplier),
            'Annual' => $base->addYears($multiplier),
            default => null,
        };

        if ($next instanceof CarbonImmutable) {
            return $next;
        }

        // Generic time units (case-insensitive)
        return match (strtolower($unit)) {
            'minute', 'minutes' => $base->addMinutes($multiplier),
            'hour', 'hours' => $base->addHours($multiplier),
            'day', 'days' => $base->addDays($multiplier),
            'week', 'weeks' => $base->addWeeks($multiplier),
            'month', 'months' => $base->addMonths($multiplier),
            'year', 'years' => $base->addYears($multiplier),
            default => null,
        };
    }
}
