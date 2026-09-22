<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\InvalidCsv;
use App\Infrastructure\DateParser;
use App\Validator\AssignmentRecordValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AssignmentRecordValidatorTest extends TestCase
{
    public function testNormalizesOnlyAfterValidation(): void
    {
        self::assertSame([
            'employeeId' => 143, 'projectId' => 12, 'dateFrom' => '2024-02-01', 'dateTo' => null,
        ], $this->validator()->validateAndNormalize([
            'employeeId' => '143', 'projectId' => ' 12 ', 'dateFrom' => '01/02/2024', 'dateTo' => null,
        ], 1, new \DateTimeImmutable('2024-02-05')));
    }

    public function testAcceptsDateObjects(): void
    {
        $result = $this->validator()->validateAndNormalize([
            'employeeId' => 1, 'projectId' => 2, 'dateFrom' => new \DateTimeImmutable('2024-02-01'), 'dateTo' => new \DateTime('2024-02-05'),
        ], 1, new \DateTimeImmutable('2024-02-05'));
        self::assertSame('2024-02-05', $result['dateTo']);
    }

    #[DataProvider('invalidRecords')]
    public function testRejectsInvalidFieldsWithRecordNumber(string $field, mixed $value): void
    {
        $record = ['employeeId' => '1', 'projectId' => '2', 'dateFrom' => '2024-02-01', 'dateTo' => '2024-02-05'];
        $record[$field] = $value;
        $this->expectException(InvalidCsv::class);
        $this->expectExceptionMessage('Record 4: '.$field);
        $this->validator()->validateAndNormalize($record, 4, new \DateTimeImmutable('2024-02-05'));
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidRecords(): iterable
    {
        yield 'partial integer' => ['employeeId', '143abc'];
        yield 'fraction' => ['employeeId', '1.5'];
        yield 'zero' => ['employeeId', 0];
        yield 'negative' => ['projectId', -1];
        yield 'overflow' => ['projectId', '2147483648'];
        yield 'blank' => ['employeeId', ''];
        yield 'boolean' => ['employeeId', true];
        yield 'missing start' => ['dateFrom', null];
        yield 'impossible date' => ['dateFrom', '2024-02-30'];
        yield 'empty end' => ['dateTo', ''];
        yield 'numeric date' => ['dateTo', 123];
        yield 'reversed period' => ['dateTo', '2024-01-31'];
    }

    public function testNullEndDateUsesTodayForRangeValidation(): void
    {
        $this->expectException(InvalidCsv::class);
        $this->expectExceptionMessage('dateTo must be on or after dateFrom');
        $this->validator()->validateAndNormalize([
            'employeeId' => '1', 'projectId' => '2', 'dateFrom' => '2024-02-06', 'dateTo' => null,
        ], 1, new \DateTimeImmutable('2024-02-05'));
    }

    public function testMissingFieldIsRejected(): void
    {
        $this->expectException(InvalidCsv::class);
        $this->expectExceptionMessage('dateTo');
        $this->validator()->validateAndNormalize([
            'employeeId' => '1', 'projectId' => '2', 'dateFrom' => '2024-02-01',
        ], 1, new \DateTimeImmutable('2024-02-05'));
    }

    private function validator(): AssignmentRecordValidator
    {
        return new AssignmentRecordValidator(Validation::createValidator(), new DateParser());
    }
}
