<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create employees_collaboration if it does not already exist.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS employees_collaboration (
                id INT AUTO_INCREMENT NOT NULL,
                empoyee_id INT NOT NULL,
                project_id INT NOT NULL,
                days_worked INT NOT NULL,
                date_from DATE NOT NULL,
                date_to DATE NOT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uniq_collaboration_assignment (empoyee_id, project_id, date_from),
                INDEX idx_collaboration_project_employee_dates (project_id, empoyee_id, date_from, date_to)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration can adopt an existing table; dropping it could destroy pre-existing assignments.');
    }
}
