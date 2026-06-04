<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528095456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD title_en VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD description_en TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD description_courte_en VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP title_en');
        $this->addSql('ALTER TABLE project DROP description_en');
        $this->addSql('ALTER TABLE project DROP description_courte_en');
    }
}
