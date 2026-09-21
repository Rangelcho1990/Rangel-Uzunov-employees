<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Employees\EmployeesCollaboration;
use PHPUnit\Framework\TestCase;

final class EmployeesCollaborationTest extends TestCase
{
    public function testInclusiveDays(): void
    {
        $assignment = new EmployeesCollaboration(1, 2, new \DateTimeImmutable('2024-02-28'), new \DateTimeImmutable('2024-03-01'));
        self::assertSame(3, $assignment->getDaysWorked());
        self::assertNull($assignment->getId());
    }

    public function testReversedDatesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EmployeesCollaboration(1, 2, new \DateTimeImmutable('2024-03-01'), new \DateTimeImmutable('2024-02-28'));
    }
}
