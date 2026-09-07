<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression douce des comptes : `app_user.deleted_at`.
 *
 * Troisième et dernier volet, avec Version20260907093448 (member) et
 * Version20260907095757 (license) : le compte lié à un licencié supprimé doit
 * disparaître avec lui, sans quoi la personne continue de se connecter.
 *
 * NULL = actif, les comptes existants ne bougent pas. La diff proposait aussi
 * de retirer les DEFAULT sur les colonnes legal_rep_* : dérive de schéma
 * antérieure, sans rapport, volontairement laissée de côté.
 */
final class Version20260907120807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute app_user.deleted_at (suppression douce, en cascade depuis le licencié)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP deleted_at');
    }
}
