<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

interface ListCollaborationServiceInterface
{
    public function hasAssignments(): bool;

    /** @return array{firstEmployeeId: int, secondEmployeeId: int, totalDays: int, projectDays: array<int, int>}|null */
    public function getList(): ?array;
}
