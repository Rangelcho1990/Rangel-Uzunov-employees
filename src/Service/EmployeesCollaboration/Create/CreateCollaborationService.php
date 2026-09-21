<?php

declare(strict_types=1);

namespace App\Service\EmployeesCollaboration\Create;

use App\Entity\Employees\EmployeesCollaboration;
use App\Exception\InvalidCsv;
use App\Infrastructure\DateParser;
use App\Infrastructure\FileUpload\AssignmentReaderInterface;
use App\Infrastructure\SystemTodayProvider;
use App\Repository\EmployeesCollaboration\Create\CreateEmployeesCollaborationInterface;
use App\Service\EmployeesCollaboration\Create\DTO\FileParsedDataDTO;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CreateCollaborationService implements CreateCollaborationServiceInterface
{
    public function __construct(
        private AssignmentReaderInterface $reader,
        private CreateEmployeesCollaborationInterface $repository,
        private Connection $connection,
        private DateParser $dates,
        private SystemTodayProvider $clock,
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
        $today = $this->clock->today();

        return $this->connection->transactional(function () use ($formData, $today): int {
            $batch = [];
            $count = 0;

            foreach ($formData->data as $index => $record) {
                try {
                    $assignment = new EmployeesCollaboration(
                        $record['employeeId'],
                        $record['projectId'],
                        $this->parseDate($record['dateFrom']),
                        null === $record['dateTo'] ? $today : $this->parseDate($record['dateTo']),
                    );

                    array_push(
                        $batch,
                        $assignment->getEmployeeId(),
                        $assignment->getProjectId(),
                        $assignment->getDaysWorked(),
                        $assignment->getDateFrom()->format('Y-m-d'),
                        $assignment->getDateTo()->format('Y-m-d')
                    );
                } catch (\InvalidArgumentException|\ValueError $exception) {
                    throw new InvalidCsv(sprintf('Record %d: %s', $index + 1, $exception->getMessage()), previous: $exception);
                }
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

    private function parseDate(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->dates->parse($value->format('Y-m-d'));
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Dates must be date strings or DateTime objects.');
        }

        return $this->dates->parse(trim($value));
    }
}
