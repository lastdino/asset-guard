<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Services\Inspections;

final class InspectionOutcomeService
{
    /**
     * Build standardized [result, value] from a generic form payload.
     *
     * @param array{
     *   method?:string|null,
     *   result?:string|null,
     *   number?:mixed,
     *   text?:string|null,
     *   select?:string|null,
     *   min?:float|int|null,
     *   max?:float|int|null
     * } $payload
     * @return array{0:string,1:?string}
     */
    public function fromArray(array $payload): array
    {
        $method = $payload['method'] ?? null;

        // Number: evaluate pass/fail against optional min/max and store numeric as string
        if ($method === 'number') {
            $raw = $payload['number'] ?? null;
            $value = ($raw === '' || $raw === null) ? null : (float) $raw;
            if ($value === null) {
                return ['Pass', null];
            }
            $pass = true;
            if (array_key_exists('min', $payload) && $payload['min'] !== null) {
                $pass = $pass && $value >= (float) $payload['min'];
            }
            if (array_key_exists('max', $payload) && $payload['max'] !== null) {
                $pass = $pass && $value <= (float) $payload['max'];
            }

            return [$pass ? 'Pass' : 'Fail', (string) $value];
        }

        // Boolean: store value as same as result (Pass/Fail)
        if ($method === 'boolean') {
            $result = ($payload['result'] ?? 'Pass') ?: 'Pass';

            return [$result, $result];
        }

        return match ($method) {
            'text' => ['Pass', $payload['text'] ?? null],
            'select' => ['Pass', $payload['select'] ?? null],
            default => ['Pass', null],
        };
    }
}
