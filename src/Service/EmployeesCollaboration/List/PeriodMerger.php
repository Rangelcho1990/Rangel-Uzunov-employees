<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

final class PeriodMerger
{
    /**
     * Produces sorted, non-overlapping ranges for one employee on one project.
     * Each range contains inclusive start and end day numbers.
     *
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    public function merge(array $ranges): array
    {
        // Sorting ensures only the last merged range can overlap the next input range.
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        // Extend the current range for overlaps; otherwise preserve the gap with a new range.
        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);
            if (null !== $last && $start <= $merged[$last][1]) {
                // A nested or duplicate range must not shorten the existing end date.
                $merged[$last][1] = max($end, $merged[$last][1]);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
