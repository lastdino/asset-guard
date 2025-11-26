<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services\Inspections;

use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem;

final class ChecklistOptionsService
{
    /**
     * @return array<int, string>
     */
    public function extract(AssetGuardInspectionChecklistItem $item): array
    {
        // Prefer pass_condition.options when available
        $pc = $item->pass_condition ?? null;
        if (is_array($pc) && isset($pc['options']) && is_array($pc['options'])) {
            return collect($pc['options'])
                ->map(static fn($v) => (string) $v)
                ->filter()
                ->values()
                ->all();
        }

        // Fallback to legacy comma-separated choices
        if (property_exists($item, 'choices') && $item->choices) {
            return collect(explode(',', (string) $item->choices))
                ->map(static fn($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }
}
