<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\Create;

use App\Exception\InvalidCsv;
use App\Infrastructure\FileUpload\AssignmentReaderInterface;
use App\Repository\EmployeesCollaboration\Create\CreateEmployeesCollaborationInterface;
use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;
use App\Validator\AssignmentRecordValidator;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CreateCollaborationService implements CreateCollaborationServiceInterface
{
    public function __construct(
        private AssignmentReaderInterface $reader,
        private CreateEmployeesCollaborationInterface $repository,
        private Connection $connection,
        private AssignmentRecordValidator $records,
    ) {
    }

    public function validateFormData(FormInterface $form): FileParsedDataDTO
    {
        if (false === $form->isSubmitted()) {
            throw new InvalidCsv('File was not submitted!');
        }

        if (false === $form->isValid()) {
            throw new InvalidCsv('File was not valid!');
        }

        $file = $form->get('file')->getData();
        if (false === $file instanceof UploadedFile) {
            throw new InvalidCsv('File was not uploaded!');
        }

        $fileData = $this->reader->read(
            $file->getPathname()
        );

        if ([] === $fileData->data) {
            throw new InvalidCsv('The file is empty!');
        }

        return $fileData;
    }

    public function insertBatch(FileParsedDataDTO $formData): int
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        return $this->connection->transactional(function () use ($formData, $today): int {
            $batch = [];
            $count = 0;

            foreach ($formData->data as $index => $record) {
                $assignment = $this->records->validateAndNormalize($record, $index + 1, $today);
                array_push(
                    $batch,
                    $assignment['employeeId'],
                    $assignment['projectId'],
                    $assignment['dateFrom'],
                    $assignment['dateTo'],
                );
                ++$count;

                if (0 === $count % 500) {
                    $this->repository->insertBatch($batch);
                    $batch = [];
                }
            }

            if ([] !== $batch) {
                $this->repository->insertBatch($batch);
            }

            return $count;
        });
    }
}
