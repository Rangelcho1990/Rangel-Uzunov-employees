<?php

declare(strict_types=1);

namespace App\Repository\EmployeesCollaboration\List;

interface ListEmployeesCollaborationInterface
{
    public function hasAssignments(): bool;

    /** @return list<array<string, mixed>> */
    public function findLongestTeam(): array;
}
