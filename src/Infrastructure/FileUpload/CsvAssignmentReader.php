<?php

declare(strict_types=1);

namespace App\Infrastructure\FileUpload;

use App\Exception\InvalidCsv;
use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;

final readonly class CsvAssignmentReader implements AssignmentReaderInterface
{
    public function read(string $path): FileParsedDataDTO
    {
        $stream = @fopen($path, 'rb');
        if (false === $stream) {
            throw new InvalidCsv('The uploaded file could not be read. Please try again.');
        }

        $rows = [];
        $record = 0;
        $firstRecord = true;

        try {
            while (false !== ($row = fgetcsv($stream, null, ',', '"', ''))) {
                ++$record;
                if ([null] === $row) {
                    continue;
                }
                if ($firstRecord) {
                    $firstRecord = false;
                    $header = array_map(static fn (?string $value): string => trim($value ?? ''), $row);
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
                    if (['EmpID', 'ProjectID', 'DateFrom', 'DateTo'] === $header) {
                        continue;
                    }
                }
                if (4 !== count($row)) {
                    throw new InvalidCsv(sprintf('Row %d: expected four columns (EmpID, ProjectID, DateFrom, DateTo).', $record));
                }
                $rows[] = [
                    'employeeId' => $row[0],
                    'projectId' => $row[1],
                    'dateFrom' => $row[2],
                    'dateTo' => null !== $row[3] && 'NULL' === strtoupper(trim($row[3])) ? null : $row[3],
                ];
            }
        } finally {
            fclose($stream);
        }

        return new FileParsedDataDTO($rows);
    }
}
