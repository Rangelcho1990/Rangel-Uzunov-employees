<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\List;

use App\Repository\EmployeesCollaboration\List\ListEmployeesCollaborationInterface;

final readonly class ListCollaborationService implements ListCollaborationServiceInterface
{
    /** Connects assignment storage to the calculation service. */
    public function __construct(
        private ListEmployeesCollaborationInterface $repository,
        private LongestTeamCalculator $calculator,
    ) {
    }

    /** Lets the page distinguish an empty database from assignments without overlap. */
    public function hasAssignments(): bool
    {
        return $this->repository->hasAssignments();
    }

    /**
     * Loads stored assignments and returns the winning pair, or null when none overlap.
     *
     * @return array{firstEmployeeId: int, secondEmployeeId: int, totalDays: int, projectDays: array<int, int>}|null
     */
    public function getList(): ?array
    {
        return $this->calculator->findLongestTeam(
            $this->repository->findLongestTeam()
        );
    }
}
