<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\Create;

use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;
use Symfony\Component\Form\FormInterface;

interface CreateCollaborationServiceInterface
{
    public function validateFormData(FormInterface $form): FileParsedDataDTO;

    public function insertBatch(FileParsedDataDTO $formData): int;
}
