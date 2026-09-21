<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Employees\EmployeesCollaboration;
use App\Exception\InvalidCsv;
use App\Infrastructure\FileUpload\CsvAssignmentReader;
use App\Repository\EmployeesCollaboration\List\ListEmployeesCollaborationRepository;
use App\Service\EmployeesCollaboration\Create\CreateCollaborationService;
use App\Service\EmployeesCollaboration\List\ListCollaborationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseImportTest extends KernelTestCase
{
    private Connection $connection;
    private CreateCollaborationService $importer;
    private ListEmployeesCollaborationRepository $repository;
    private string $path;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->executeStatement('DELETE FROM employees_collaboration');
        $importer = self::getContainer()->get(CreateCollaborationService::class);
        self::assertInstanceOf(CreateCollaborationService::class, $importer);
        $this->importer = $importer;
        $repository = self::getContainer()->get(ListEmployeesCollaborationRepository::class);
        self::assertInstanceOf(ListEmployeesCollaborationRepository::class, $repository);
        $this->repository = $repository;
        $path = tempnam(sys_get_temp_dir(), 'sirma-csv-');
        self::assertNotFalse($path);
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        parent::tearDown();
    }

    public function testNullEndDateIsStoredAndUsedAsToday(): void
    {
        $today = gmdate('Y-m-d');
        $this->import("1,10,$today,NULL\n2,10,$today,$today");
        self::assertNull($this->connection->fetchOne('SELECT date_to FROM employees_collaboration WHERE empoyee_id = 1'));
        $entity = $this->repository->findOneBy(['employeeId' => 1]);
        self::assertInstanceOf(EmployeesCollaboration::class, $entity);
        self::assertNull($entity->getDateTo());
        self::assertSame(1, $entity->getDaysWorked());
        $result = (new ListCollaborationService($this->repository))->getList();
        self::assertNotNull($result);
        self::assertSame(1, $result['totalDays']);
    }

    public function testReuploadUpdatesAssignmentAndEntityIsHydrated(): void
    {
        $this->import("1,10,2024-01-01,2024-01-05\n2,10,2024-01-01,2024-01-10");
        $this->import('1,10,2024-01-01,2024-01-08');
        self::assertSame(2, $this->repository->count([]));
        $result = (new ListCollaborationService($this->repository))->getList();
        self::assertNotNull($result);
        self::assertSame(8, $result['totalDays']);
        $entity = $this->repository->findOneBy(['employeeId' => 1]);
        self::assertInstanceOf(EmployeesCollaboration::class, $entity);
        self::assertNotNull($entity->getId());
        self::assertSame(8, $entity->getDaysWorked());
    }

    public function testOverlappingNestedDisjointPeriodsAndProjectTotals(): void
    {
        $this->import("1,10,2024-01-01,2024-01-10\n1,10,2024-01-03,2024-01-04\n1,10,2024-01-08,2024-01-12\n1,10,2024-01-20,2024-01-22\n2,10,2024-01-01,2024-01-31\n2,10,2024-01-02,2024-01-30\n1,20,2024-01-01,2024-01-02\n2,20,2024-01-02,2024-01-03");
        self::assertSame(['firstEmployeeId' => 1, 'secondEmployeeId' => 2, 'totalDays' => 16, 'projectDays' => [10 => 15, 20 => 1]], (new ListCollaborationService($this->repository))->getList());
    }

    public function testTiesAndNoOverlap(): void
    {
        self::assertSame(0, $this->repository->count([]));
        $this->import("10,10,2024-01-01,2024-01-05\n3,10,2024-01-01,2024-01-05\n2,10,2024-01-01,2024-01-05");
        $result = (new ListCollaborationService($this->repository))->getList();
        self::assertNotNull($result);
        self::assertSame(2, $result['firstEmployeeId']);
        self::assertSame(3, $result['secondEmployeeId']);
    }

    public function testInvalidLateRowRollsBackBothInsertsAndUpdates(): void
    {
        $this->import('1,10,2024-01-01,2024-01-05');
        $csv = "1,10,2024-01-01,2024-01-10\n";
        for ($i = 2; $i <= 600; ++$i) {
            $csv .= "$i,10,2024-01-01,2024-01-05\n";
        }
        try {
            $this->import($csv.'601,10,not-a-date,2024-01-05');
            self::fail('Expected invalid CSV.');
        } catch (InvalidCsv) {
            self::assertSame(1, $this->repository->count([]));
            self::assertEquals(5, $this->connection->fetchOne('SELECT days_worked FROM employees_collaboration'));
        }
    }

    public function testTenThousandRowsWithNoApplicationLimit(): void
    {
        $csv = '';
        for ($i = 1; $i <= 5000; ++$i) {
            $csv .= "1,$i,2024-01-01,2024-01-02\n2,$i,2024-01-01,2024-01-02\n";
        }
        self::assertSame(10000, $this->import($csv));
        self::assertSame(10000, $this->repository->count([]));
        $result = (new ListCollaborationService($this->repository))->getList();
        self::assertNotNull($result);
        self::assertSame(10000, $result['totalDays']);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        self::assertSame(0, $manager->getUnitOfWork()->size());
    }

    private function import(string $csv): int
    {
        file_put_contents($this->path, $csv);

        return $this->importer->insertBatch((new CsvAssignmentReader())->read($this->path));
    }
}
