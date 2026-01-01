<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101210833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add color field to Member entity';
    }

    public function up(Schema $schema): void
    {
        // Add color column as nullable first
        $this->addSql('ALTER TABLE member ADD color VARCHAR(7) DEFAULT NULL');

        // Update existing members with random hex colors using SQL (PostgreSQL)
        $this->addSql("UPDATE member SET color = CONCAT('#', UPPER(LPAD(to_hex(FLOOR(RANDOM() * 16777215)::int), 6, '0')))");

        // Make the column NOT NULL
        $this->addSql('ALTER TABLE member ALTER COLUMN color SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member DROP color');
    }
}
