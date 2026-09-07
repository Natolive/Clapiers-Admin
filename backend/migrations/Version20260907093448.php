<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression douce des licenciés : `member.deleted_at`.
 *
 * NULL = vivant, ce qui laisse toutes les lignes existantes intactes. Le
 * filtre Doctrine `soft_deleted` s'appuie sur cette colonne.
 *
 * La diff générée proposait aussi de retirer les DEFAULT sur les colonnes
 * legal_rep_* : dérive de schéma antérieure, sans rapport avec ce changement,
 * volontairement laissée de côté.
 */
final class Version20260907093448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute member.deleted_at (suppression douce)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP deleted_at');
    }
}
