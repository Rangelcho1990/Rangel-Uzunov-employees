<?php

declare(strict_types=1);

namespace App\Repository\EmployeesCollaboration\List;

use App\Entity\Employees\EmployeesCollaboration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmployeesCollaboration> */
final class ListEmployeesCollaborationRepository extends ServiceEntityRepository implements ListEmployeesCollaborationInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeesCollaboration::class);
    }

    public function hasAssignments(): bool
    {
        return $this->count([]) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function findLongestTeam(): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, empoyee_id, project_id, date_from, date_to FROM employees_collaboration',
        );
    }
}
