<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

final readonly class LongestTeamCalculator
{
    /** Supplies the period normalization and shared-day calculations. */
    public function __construct(private PeriodMerger $periodMerger, private OverlapCalculator $overlapCalculator)
    {
    }

    /**
     * Finds the pair with the greatest sum of shared project days.
     * Both date boundaries count; ties favor the numerically lowest employee IDs.
     *
     * @param list<array<string, mixed>> $data
     *
     * @return array{firstEmployeeId: int, secondEmployeeId: int, totalDays: int, projectDays: array<int, int>}|null
     */
    public function findLongestTeam(array $data): ?array
    {
        // Use one UTC date for every ongoing assignment in this calculation.
        $today = gmdate('Y-m-d');
        $periods = [];

        // Group assignment ranges by project and employee, preserving separate work periods.
        foreach ($data as $row) {
            $project = $this->integer($row['project_id']);
            $employee = $this->integer($row['empoyee_id']);

            $dateFrom = $this->day($row['date_from']);
            $dateTo = $this->day($row['date_to'] ?? $today);

            // Convert UTC dates to whole-day numbers for inclusive interval arithmetic.
            $periods[$project][$employee][] = [
                (int) floor($dateFrom->getTimestamp() / 86400),
                (int) floor($dateTo->getTimestamp() / 86400),
            ];
        }

        $pairs = [];
        // Employees can contribute shared days only when assigned to the same project.
        foreach ($periods as $project => $users) {
            // Stable pair ordering gives the same key for an employee pair on every project.
            ksort($users, SORT_NUMERIC);
            $employeeIds = array_keys($users);

            // Merge duplicate and overlapping assignments to avoid counting a day twice.
            foreach ($employeeIds as $employee) {
                $periods[$project][$employee] = $this->periodMerger->merge($periods[$project][$employee]);
            }

            // Choose the first employee of each distinct pair on this project.
            foreach ($employeeIds as $index => $first) {
                // Compare only later IDs: exclude self-pairs and reversed duplicates.
                foreach (array_slice($employeeIds, $index + 1) as $second) {
                    $days = $this->overlapCalculator->calculate($periods[$project][$first], $periods[$project][$second]);
                    if (0 === $days) {
                        continue;
                    }
                    $key = $first.':'.$second;
                    $pairs[$key] ??= ['firstEmployeeId' => $first, 'secondEmployeeId' => $second, 'totalDays' => 0, 'projectDays' => []];
                    // Keep the project breakdown and accumulate this pair's cross-project total.
                    $pairs[$key]['projectDays'][$project] = $days;
                    $pairs[$key]['totalDays'] += $days;
                }
            }
        }

        $winner = null;
        // Select the greatest total; resolve equal totals by the two employee IDs.
        foreach ($pairs as $pair) {
            if (null === $winner || $pair['totalDays'] > $winner['totalDays']
                || ($pair['totalDays'] === $winner['totalDays']
                    && [$pair['firstEmployeeId'], $pair['secondEmployeeId']] < [$winner['firstEmployeeId'], $winner['secondEmployeeId']])) {
                $winner = $pair;
            }
        }
        if (null !== $winner) {
            // Present project rows in a predictable numeric order.
            ksort($winner['projectDays'], SORT_NUMERIC);
        }

        return $winner;
    }

    /** Normalizes IDs returned by the database driver as integers or digit strings. */
    private function integer(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException('Expected an integer database value.');
        }

        return (int) $value;
    }

    /** Parses a database date at UTC midnight and rejects invalid calendar values. */
    private function day(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Expected a database date string.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (false === $date || false !== \DateTimeImmutable::getLastErrors()) {
            throw new \UnexpectedValueException('Invalid database date.');
        }

        return $date;
    }
}
