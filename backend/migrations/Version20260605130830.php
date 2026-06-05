<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605130830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove read/unread tracking from contact_message (keep message history only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP CONSTRAINT fk_2c9211fef5675cd0');
        $this->addSql('DROP INDEX idx_2c9211fef5675cd0');
        $this->addSql('ALTER TABLE contact_message DROP is_read');
        $this->addSql('ALTER TABLE contact_message DROP read_at');
        $this->addSql('ALTER TABLE contact_message DROP read_by_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message ADD is_read BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE contact_message ADD read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_message ADD read_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT fk_2c9211fef5675cd0 FOREIGN KEY (read_by_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2c9211fef5675cd0 ON contact_message (read_by_id)');
    }
}
