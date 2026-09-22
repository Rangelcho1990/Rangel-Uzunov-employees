<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\EmployeesCollaboration\List\PeriodMerger;
use PHPUnit\Framework\TestCase;

final class PeriodMergerTest extends TestCase
{
    public function testMergesUnsortedDuplicateNestedAndOverlappingPeriods(): void
    {
        self::assertSame([[1, 12], [20, 22]], (new PeriodMerger())->merge([
            [20, 22], [3, 4], [1, 10], [8, 12], [1, 10],
        ]));
    }

    public function testSharedBoundaryIsMergedButDisjointPeriodsRemainSeparate(): void
    {
        self::assertSame([[1, 5], [8, 9]], (new PeriodMerger())->merge([[1, 3], [3, 5], [8, 9]]));
        self::assertSame([], (new PeriodMerger())->merge([]));
    }
}
