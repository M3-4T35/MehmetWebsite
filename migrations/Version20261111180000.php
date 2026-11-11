<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_setting table (stores the active site design)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_setting (name VARCHAR(64) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY (name))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_setting');
    }
}
