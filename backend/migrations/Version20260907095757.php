<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression douce des licences : `license.deleted_at`.
 *
 * Complète Version20260907093448 (member.deleted_at). Les deux vont ensemble :
 * une licence est atteignable par son `access_token` sans passer par le membre,
 * donc si elle survivait à un membre supprimé, le magic link public de paiement
 * répondrait 500 (`getMember()` non nullable sur une ligne masquée).
 *
 * NULL = vivante, les lignes existantes ne bougent pas. La diff proposait aussi
 * de retirer les DEFAULT sur les colonnes legal_rep_* : dérive de schéma
 * antérieure, sans rapport, volontairement laissée de côté.
 */
final class Version20260907095757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute license.deleted_at (suppression douce, en cascade depuis le membre)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license DROP deleted_at');
    }
}
