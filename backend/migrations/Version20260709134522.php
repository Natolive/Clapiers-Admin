<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260709134522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attestation santé (license) + représentant légal du mineur (member, embeddable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license ADD health_declaration BOOLEAN DEFAULT NULL');
        // DEFAULT '' : le représentant légal n'existe que pour les mineurs ; les
        // membres existants (et majeurs) portent des chaînes vides.
        $this->addSql("ALTER TABLE member ADD legal_rep_first_name VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE member ADD legal_rep_last_name VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE member ADD legal_rep_email VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE member ADD legal_rep_phone VARCHAR(30) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license DROP health_declaration');
        $this->addSql('ALTER TABLE member DROP legal_rep_first_name');
        $this->addSql('ALTER TABLE member DROP legal_rep_last_name');
        $this->addSql('ALTER TABLE member DROP legal_rep_email');
        $this->addSql('ALTER TABLE member DROP legal_rep_phone');
    }
}
