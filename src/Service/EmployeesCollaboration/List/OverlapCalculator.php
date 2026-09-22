<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

final class OverlapCalculator
{
    /**
     * Counts shared days between two employees on one project using two pointers.
     * Both inputs must be sorted, non-overlapping inclusive day ranges.
     *
     * @param list<array{int, int}> $first
     * @param list<array{int, int}> $second
     */
    public function calculate(array $first, array $second): int
    {
        $i = $j = $days = 0;
        // Walk both lists once; stop when either employee has no periods left.
        while (isset($first[$i], $second[$j])) {
            $start = max($first[$i][0], $second[$j][0]);
            $end = min($first[$i][1], $second[$j][1]);

            // Include both boundaries; disjoint ranges contribute zero days.
            $days += max(0, $end - $start + 1);

            // Advance the period that ends first: it cannot overlap any later opposite period.
            if ($first[$i][1] <= $second[$j][1]) {
                ++$i;
            } else {
                ++$j;
            }
        }

        return $days;
    }
}
