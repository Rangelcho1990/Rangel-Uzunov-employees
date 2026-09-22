<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\Create\DTO;

final readonly class FileParsedDataDTO
{
    /** @param list<array{employeeId: int|string|null, projectId: int|string|null, dateFrom: mixed, dateTo: mixed}> $data */
    public function __construct(public array $data = [])
    {
    }
}
