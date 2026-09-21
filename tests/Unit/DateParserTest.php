<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\DateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateParserTest extends TestCase
{
    #[DataProvider('validDates')]
    public function testSupportedFormats(string $input): void
    {
        self::assertSame('2024-02-29', (new DateParser())->parse($input)->format('Y-m-d'));
    }

    /** @return iterable<string, array{string}> */
    public static function validDates(): iterable
    {
        foreach (['2024-02-29', '2024/02/29', '29/02/2024', '29.02.2024', '29-02-2024'] as $date) {
            yield 'date: '.$date => [$date];
        }
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDatesAreRejected(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DateParser())->parse($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDates(): iterable
    {
        foreach (['2023-02-29', '2024-04-31', 'tomorrow', '', '02/29/2024', '2024-01-01junk'] as $date) {
            yield 'date: '.$date => [$date];
        }
    }

    public function testSlashDatesAreDayFirst(): void
    {
        self::assertSame('2024-02-01', (new DateParser())->parse('01/02/2024')->format('Y-m-d'));
    }
}
