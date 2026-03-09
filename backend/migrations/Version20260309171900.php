<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309171900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game ADD date DATE NOT NULL');
        $this->addSql('ALTER TABLE game ADD meeting_time VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE game DROP start');
        $this->addSql('ALTER TABLE game DROP "end"');
        $this->addSql('ALTER TABLE game DROP all_day');
        $this->addSql('ALTER TABLE game DROP description');
        $this->addSql('ALTER TABLE game RENAME COLUMN title TO opponent');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game ADD start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE game ADD "end" TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD all_day BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE game ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE game DROP date');
        $this->addSql('ALTER TABLE game DROP meeting_time');
        $this->addSql('ALTER TABLE game RENAME COLUMN opponent TO title');
    }
}
