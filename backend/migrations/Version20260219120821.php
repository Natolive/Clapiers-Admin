<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219120821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD member_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E97597D3FE FOREIGN KEY (member_id) REFERENCES member (id) NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E97597D3FE ON app_user (member_id)');
        $this->addSql('ALTER TABLE member ALTER license_paid SET DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT FK_88BDF3E97597D3FE');
        $this->addSql('DROP INDEX UNIQ_88BDF3E97597D3FE');
        $this->addSql('ALTER TABLE app_user DROP member_id');
        $this->addSql('ALTER TABLE member ALTER license_paid DROP DEFAULT');
    }
}
