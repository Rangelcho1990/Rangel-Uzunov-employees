<?php

declare(strict_types=1);

namespace App\Validator;

use App\Exception\InvalidCsv;
use App\Infrastructure\DateParser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AssignmentRecordValidator
{
    private const MAX_DATABASE_ID = 2_147_483_647;

    public function __construct(private ValidatorInterface $validator, private DateParser $dates)
    {
    }

    /**
     * Validate raw values before converting them to database parameters.
     *
     * @param array<string, mixed> $record
     *
     * @return array{employeeId: int, projectId: int, dateFrom: string, dateTo: ?string}
     */
    public function validateAndNormalize(array $record, int $recordNumber, \DateTimeImmutable $today): array
    {
        $idConstraints = new Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Type(['int', 'string']),
            new Assert\Regex(pattern: '/^\s*[0-9]+\s*$/D', message: 'Must be a positive integer.'),
            new Assert\Callback(function (mixed $value, ExecutionContextInterface $context): void {
                if (false === $this->identifier($value)) {
                    $context->buildViolation('Must be an integer between 1 and {{ maximum }}.')
                        ->setParameter('{{ maximum }}', (string) self::MAX_DATABASE_ID)->addViolation();
                }
            }),
        ]);

        $dateConstraint = new Assert\Callback(function (mixed $value, ExecutionContextInterface $context): void {
            if (null === $value) {
                return;
            }
            try {
                $this->parseDate($value);
            } catch (\InvalidArgumentException|\ValueError $exception) {
                $context->buildViolation($exception->getMessage())->addViolation();
            }
        });

        $violations = $this->validator->validate($record, new Assert\Collection(fields: [
            'employeeId' => $idConstraints,
            'projectId' => $idConstraints,
            'dateFrom' => [new Assert\NotBlank(), $dateConstraint],
            'dateTo' => $dateConstraint,
        ]));

        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = trim($violation->getPropertyPath(), '[]').': '.$violation->getMessage();
            }
            throw new InvalidCsv(sprintf('Record %d: %s', $recordNumber, implode(' ', $messages)));
        }

        $from = $this->parseDate($record['dateFrom']);
        $to = null === $record['dateTo'] ? null : $this->parseDate($record['dateTo']);
        $violations = $this->validator->validate($record, new Assert\Callback(
            static function (mixed $value, ExecutionContextInterface $context) use ($from, $to, $today): void {
                if ($from > ($to ?? $today)) {
                    $context->buildViolation('Must be on or after dateFrom (NULL means today).')->atPath('[dateTo]')->addViolation();
                }
            },
        ));
        if (count($violations) > 0) {
            throw new InvalidCsv(sprintf('Record %d: dateTo must be on or after dateFrom (NULL means today).', $recordNumber));
        }
        $employee = $this->identifier($record['employeeId']);
        $project = $this->identifier($record['projectId']);
        if (false === $employee || false === $project) {
            throw new \LogicException('Validated identifiers must be integers.');
        }

        return ['employeeId' => $employee, 'projectId' => $project, 'dateFrom' => $from->format('Y-m-d'), 'dateTo' => $to?->format('Y-m-d')];
    }

    private function identifier(mixed $value): int|false
    {
        if (!is_int($value) && !is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_DATABASE_ID]]);
    }

    private function parseDate(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid date: use a supported date string or DateTime object.');
        }

        return $this->dates->parse(trim($value));
    }
}
