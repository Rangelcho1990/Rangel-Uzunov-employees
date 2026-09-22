<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\EmployeesCollaboration\List\OverlapCalculator;
use PHPUnit\Framework\TestCase;

final class OverlapCalculatorTest extends TestCase
{
    public function testCountsInclusiveIntersectionsAcrossSeparatePeriods(): void
    {
        $calculator = new OverlapCalculator();
        self::assertSame(7, $calculator->calculate([[1, 5], [10, 15]], [[5, 10], [11, 20]]));
        self::assertSame(7, $calculator->calculate([[5, 10], [11, 20]], [[1, 5], [10, 15]]));
    }

    public function testEmptyAndDisjointPeriodsHaveNoOverlap(): void
    {
        $calculator = new OverlapCalculator();
        self::assertSame(0, $calculator->calculate([], [[1, 5]]));
        self::assertSame(0, $calculator->calculate([[1, 5]], []));
        self::assertSame(0, $calculator->calculate([[1, 5]], [[6, 10]]));
    }
}
