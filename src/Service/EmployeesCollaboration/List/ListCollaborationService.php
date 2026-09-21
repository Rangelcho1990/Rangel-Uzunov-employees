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
        $projects = [];
        $data = $this->repository->findLongestTeam();

        foreach ($data as $row) {
            $project = $this->integer($row['project_id']);
            $employee = $this->integer($row['empoyee_id']);
            $projects[$project][$employee][] = [$this->day($row['date_from']), $this->day($row['date_to'])];
        }

        $pairs = [];
        foreach ($projects as $project => $employees) {
            $periods = [];
            foreach ($employees as $employee => $ranges) {
                foreach ($this->merge($ranges) as [$start, $end]) {
                    $periods[] = ['employee' => $employee, 'start' => $start, 'end' => $end];
                }
            }
            usort($periods, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
            $active = [];
            foreach ($periods as $period) {
                // Only compare with periods that still overlap the current start date.
                foreach ($active as $index => $other) {
                    if ($other['end'] < $period['start']) {
                        unset($active[$index]);
                        continue;
                    }
                    if ($other['employee'] === $period['employee']) {
                        continue;
                    }
                    $first = min($other['employee'], $period['employee']);
                    $second = max($other['employee'], $period['employee']);
                    $key = $first.':'.$second;
                    $days = min($other['end'], $period['end']) - $period['start'] + 1;
                    $pairs[$key] ??= ['firstEmployeeId' => $first, 'secondEmployeeId' => $second, 'totalDays' => 0, 'projectDays' => []];
                    $pairs[$key]['totalDays'] += $days;
                    $pairs[$key]['projectDays'][$project] = ($pairs[$key]['projectDays'][$project] ?? 0) + $days;
                }
                $active[] = $period;
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

    private function integer(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException('Expected an integer database value.');
        }

        return (int) $value;
    }

    private function day(mixed $value): int
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Expected a database date string.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (false === $date || false !== \DateTimeImmutable::getLastErrors()) {
            throw new \UnexpectedValueException('Invalid database date.');
        }

        return (int) floor($date->getTimestamp() / 86400);
    }
}
