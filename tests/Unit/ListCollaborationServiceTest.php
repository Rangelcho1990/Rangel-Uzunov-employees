<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Repository\EmployeesCollaboration\List\ListEmployeesCollaborationInterface;
use App\Service\EmployeesCollaboration\List\ListCollaborationService;
use PHPUnit\Framework\TestCase;

final class ListCollaborationServiceTest extends TestCase
{
    public function testPairsAccumulateProjectsWithoutDoubleCountingPeriods(): void
    {
        $repository = $this->createStub(ListEmployeesCollaborationInterface::class);
        $repository->method('findLongestTeam')->willReturn([
            ['empoyee_id' => 1, 'project_id' => 10, 'date_from' => '2024-01-01', 'date_to' => '2024-01-10'],
            ['empoyee_id' => 1, 'project_id' => 10, 'date_from' => '2024-01-03', 'date_to' => '2024-01-08'],
            ['empoyee_id' => 2, 'project_id' => 10, 'date_from' => '2024-01-05', 'date_to' => '2024-01-12'],
            ['empoyee_id' => 1, 'project_id' => 12, 'date_from' => '2024-02-01', 'date_to' => '2024-02-02'],
            ['empoyee_id' => 2, 'project_id' => 12, 'date_from' => '2024-02-02', 'date_to' => '2024-02-03'],
        ]);
        self::assertSame([
            'firstEmployeeId' => 1, 'secondEmployeeId' => 2,
            'totalDays' => 7, 'projectDays' => [10 => 6, 12 => 1],
        ], (new ListCollaborationService($repository))->getList());
    }

    public function testNoRecordsReturnsNull(): void
    {
        $repository = $this->createStub(ListEmployeesCollaborationInterface::class);
        $repository->method('findLongestTeam')->willReturn([]);
        self::assertNull((new ListCollaborationService($repository))->getList());
    }

    public function testDisjointPeriodsDoNotFormTeam(): void
    {
        $repository = $this->createStub(ListEmployeesCollaborationInterface::class);
        $repository->method('findLongestTeam')->willReturn([
            ['empoyee_id' => 1, 'project_id' => 1, 'date_from' => '2024-01-01', 'date_to' => '2024-01-02'],
            ['empoyee_id' => 2, 'project_id' => 1, 'date_from' => '2024-01-03', 'date_to' => '2024-01-04'],
        ]);
        self::assertNull((new ListCollaborationService($repository))->getList());
    }

    public function testCalculatesSharedDaysRatherThanSummingAssignmentDays(): void
    {
        $repository = $this->createStub(ListEmployeesCollaborationInterface::class);
        $repository->method('findLongestTeam')->willReturn([
            ['empoyee_id' => '2', 'project_id' => '1', 'days_worked' => 10, 'date_from' => '2024-01-05', 'date_to' => '2024-01-14'],
            ['empoyee_id' => '1', 'project_id' => '1', 'days_worked' => 5, 'date_from' => '2024-01-01', 'date_to' => '2024-01-05'],
        ]);
        self::assertSame([
            'firstEmployeeId' => 1, 'secondEmployeeId' => 2,
            'totalDays' => 1, 'projectDays' => [1 => 1],
        ], (new ListCollaborationService($repository))->getList());
    }
}
