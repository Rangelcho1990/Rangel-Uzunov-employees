<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

use App\Repository\EmployeesCollaboration\List\ListEmployeesCollaborationInterface;

final readonly class ListCollaborationService implements ListCollaborationServiceInterface
{
    public function __construct(private ListEmployeesCollaborationInterface $repository)
    {
    }

    public function hasAssignments(): bool
    {
        return $this->repository->hasAssignments();
    }

    /** @return array{firstEmployeeId: int, secondEmployeeId: int, totalDays: int, projectDays: array<int, int>}|null */
    public function getList(): ?array
    {
        $today = gmdate('Y-m-d');
        $periods = [];
        $data = $this->repository->findLongestTeam();

        $collaborations = [];
        foreach ($data as $row) {
            $project = $this->integer($row['project_id']);
            $employee = $this->integer($row['empoyee_id']);

            $dateFrom = $this->day($row['date_from']);
            $dateTo = $this->day($row['date_to'] ?? $today);

            $daysWorked = (int) $dateFrom->diff($dateTo)->format('%a') + 1;

            $collaborations[$project][$employee] = $daysWorked;
            $periods[$project][$employee][] = [
                (int) floor($dateFrom->getTimestamp() / 86400),
                (int) floor($dateTo->getTimestamp() / 86400),
            ];
        }

        $pairs = [];
        foreach ($collaborations as $project => $users) {
            ksort($users, SORT_NUMERIC);
            $employeeIds = array_keys($users);
            foreach ($employeeIds as $employee) {
                $periods[$project][$employee] = $this->merge($periods[$project][$employee]);
            }

            foreach ($employeeIds as $index => $first) {
                foreach (array_slice($employeeIds, $index + 1) as $second) {
                    $days = $this->sharedDays($periods[$project][$first], $periods[$project][$second]);
                    if (0 === $days) {
                        continue;
                    }
                    $key = $first.':'.$second;
                    $pairs[$key] ??= ['firstEmployeeId' => $first, 'secondEmployeeId' => $second, 'totalDays' => 0, 'projectDays' => []];
                    $pairs[$key]['projectDays'][$project] = $days;
                    $pairs[$key]['totalDays'] += $days;
                }
            }
        }

        $winner = null;
        foreach ($pairs as $pair) {
            if (null === $winner || $pair['totalDays'] > $winner['totalDays']
                || ($pair['totalDays'] === $winner['totalDays']
                    && [$pair['firstEmployeeId'], $pair['secondEmployeeId']] < [$winner['firstEmployeeId'], $winner['secondEmployeeId']])) {
                $winner = $pair;
            }
        }
        if (null !== $winner) {
            ksort($winner['projectDays'], SORT_NUMERIC);
        }

        return $winner;
    }

    /**
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    private function merge(array $ranges): array
    {
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);
            if (null !== $last && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($end, $merged[$last][1]);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * @param list<array{int, int}> $first
     * @param list<array{int, int}> $second
     */
    private function sharedDays(array $first, array $second): int
    {
        $i = $j = $days = 0;
        while (isset($first[$i], $second[$j])) {
            $start = max($first[$i][0], $second[$j][0]);
            $end = min($first[$i][1], $second[$j][1]);
            $days += max(0, $end - $start + 1);
            if ($first[$i][1] <= $second[$j][1]) {
                ++$i;
            } else {
                ++$j;
            }
        }

        return $days;
    }

    private function integer(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException('Expected an integer database value.');
        }

        return (int) $value;
    }

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
