<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Employees\EmployeesCollaboration;
use PHPUnit\Framework\TestCase;

final class EmployeesCollaborationTest extends TestCase
{
    public function testStoresAssignmentDates(): void
    {
        $assignment = new EmployeesCollaboration(1, 2, new \DateTimeImmutable('2024-02-28'), new \DateTimeImmutable('2024-03-01'));
        self::assertSame('2024-02-28', $assignment->getDateFrom()->format('Y-m-d'));
        self::assertSame('2024-03-01', $assignment->getDateTo()?->format('Y-m-d'));
        self::assertNull($assignment->getId());
    }

    public function testReversedDatesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EmployeesCollaboration(1, 2, new \DateTimeImmutable('2024-03-01'), new \DateTimeImmutable('2024-02-28'));
    }
}
