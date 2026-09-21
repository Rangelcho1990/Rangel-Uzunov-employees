<?php

declare(strict_types=1);

namespace App\Repository\EmployeesCollaboration\Create;

use App\Entity\Employees\EmployeesCollaboration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmployeesCollaboration> */
final class CreateEmployeesCollaborationRepository extends ServiceEntityRepository implements CreateEmployeesCollaborationInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeesCollaboration::class);
    }

    /**
     * @param list<int|string|null> $assignments Flat SQL parameters in repeating groups of five:
     *                                           employee ID (int), project ID (int),
     *                                           start date (Y-m-d string), end date (Y-m-d string or null).
     *                                           The number of values must be a multiple of five.
     *                                           An empty list is allowed and performs no insert.
     */
    public function insertBatch(array $assignments): void
    {
        if ([] === $assignments) {
            return;
        }

        if (0 !== count($assignments) % 5) {
            throw new \InvalidArgumentException('Each assignment must contain exactly five SQL parameters.');
        }

        $sql = 'INSERT INTO employees_collaboration (empoyee_id, project_id, date_from, date_to)
             VALUES '.implode(', ', array_fill(0, intdiv(count($assignments), 4), '(?, ?, ?, ?, ?)')).'
             ON DUPLICATE KEY UPDATE date_to = VALUES(date_to)
         ';

        $this->getEntityManager()->getConnection()->executeStatement(
            $sql,
            $assignments,
        );
    }
}
