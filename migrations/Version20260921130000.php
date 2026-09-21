<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow ongoing assignments with date_to defaulting to NULL.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employees_collaboration MODIFY date_to DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(false !== $this->connection->fetchOne('SELECT id FROM employees_collaboration WHERE date_to IS NULL LIMIT 1'), 'Cannot restore NOT NULL while ongoing assignments exist.');
        $this->addSql('ALTER TABLE employees_collaboration MODIFY date_to DATE NOT NULL');
    }
}
