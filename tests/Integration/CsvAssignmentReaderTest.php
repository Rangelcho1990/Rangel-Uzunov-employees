<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Exception\InvalidCsv;
use App\Infrastructure\FileUpload\CsvAssignmentReader;
use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;
use PHPUnit\Framework\TestCase;

final class CsvAssignmentReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'employees-test-');
        self::assertNotFalse($path);
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testReturnsDtoContainingRecordsWithoutHeaderOrBlankRows(): void
    {
        file_put_contents($this->path, "\xEF\xBB\xBFEmpID, ProjectID, DateFrom, DateTo\n\n143, 12,not-a-date,NULL\n218,10, 2024-01-01 ,2024-02-01\n");
        $rows = (new CsvAssignmentReader())->read($this->path);

        self::assertInstanceOf(FileParsedDataDTO::class, $rows);
        self::assertEquals([
            ['employeeId' => 143, 'projectId' => 12, 'dateFrom' => 'not-a-date', 'dateTo' => null],
            ['employeeId' => 218, 'projectId' => 10, 'dateFrom' => ' 2024-01-01 ', 'dateTo' => '2024-02-01'],
        ], $rows->data);
        self::assertSame('143', $rows->data[0]['employeeId']);
        self::assertSame(' 12', $rows->data[0]['projectId']);
        self::assertNull($rows->data[0]['dateTo']);
    }

    public function testQuotedFieldsAndHeaderlessInput(): void
    {
        file_put_contents($this->path, "1,2,\"line one\nline two\",\"a,b\"\n3,4,anything, null \n5,6,anything,\n");

        self::assertEquals([
            ['employeeId' => 1, 'projectId' => 2, 'dateFrom' => "line one\nline two", 'dateTo' => 'a,b'],
            ['employeeId' => 3, 'projectId' => 4, 'dateFrom' => 'anything', 'dateTo' => null],
            ['employeeId' => 5, 'projectId' => 6, 'dateFrom' => 'anything', 'dateTo' => ''],
        ], (new CsvAssignmentReader())->read($this->path)->data);
    }

    public function testHeaderOnlyAndEmptyFileReturnDtoWithEmptyData(): void
    {
        self::assertSame([], (new CsvAssignmentReader())->read($this->path)->data);
        file_put_contents($this->path, 'EmpID,ProjectID,DateFrom,DateTo');
        self::assertSame([], (new CsvAssignmentReader())->read($this->path)->data);
    }

    public function testIncompleteRowHasClearError(): void
    {
        file_put_contents($this->path, '1,2');
        $this->expectException(InvalidCsv::class);
        $this->expectExceptionMessage('Row 1: expected four columns');
        (new CsvAssignmentReader())->read($this->path);
    }

    public function testUnreadableFileThrowsAnException(): void
    {
        $this->expectException(InvalidCsv::class);
        (new CsvAssignmentReader())->read($this->path.'/missing.csv');
    }

    public function testDtoSupportsMixedDateValues(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $dto = new FileParsedDataDTO([['employeeId' => 1, 'projectId' => 2, 'dateFrom' => $date, 'dateTo' => 123]]);
        self::assertSame($date, $dto->data[0]['dateFrom']);
        self::assertSame(123, $dto->data[0]['dateTo']);
    }
}
