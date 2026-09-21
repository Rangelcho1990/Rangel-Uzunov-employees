<?php

declare(strict_types=1);

namespace App\Infrastructure\FileUpload;

use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;

interface AssignmentReaderInterface
{
    public function read(string $path): FileParsedDataDTO;
}
