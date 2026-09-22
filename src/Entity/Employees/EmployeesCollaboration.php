<?php

declare(strict_types=1);

namespace App\Entity\Employees;

use App\Repository\EmployeesCollaboration\List\ListEmployeesCollaborationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListEmployeesCollaborationRepository::class)]
#[ORM\Table(name: 'employees_collaboration')]
#[ORM\UniqueConstraint(name: 'uniq_employee_project_start_date', columns: ['empoyee_id', 'project_id', 'date_from'])]
#[ORM\Index(name: 'idx_project_employee_date_range', columns: ['project_id', 'empoyee_id', 'date_from', 'date_to'])]
class EmployeesCollaboration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'empoyee_id')]
    private int $employeeId;

    #[ORM\Column(name: 'project_id')]
    private int $projectId;

    #[ORM\Column(name: 'date_from', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateFrom;

    #[ORM\Column(name: 'date_to', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateTo = null;

    public function __construct(int $employeeId, int $projectId, \DateTimeImmutable $dateFrom, ?\DateTimeImmutable $dateTo = null)
    {
        if (null !== $dateTo && $dateFrom > $dateTo) {
            throw new \InvalidArgumentException('The start date must be on or before the end date.');
        }

        $this->employeeId = $employeeId;
        $this->projectId = $projectId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployeeId(): int
    {
        return $this->employeeId;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getDateFrom(): \DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function getDateTo(): ?\DateTimeImmutable
    {
        return $this->dateTo;
    }
}
