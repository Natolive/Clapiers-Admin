<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression douce des équipes : `team.deleted_at`.
 *
 * Une équipe est référencée par les matchs (`game.team_id`, non nul) : la
 * supprimer vraiment effacerait l'historique sportif. Comme pour les licenciés,
 * l'écouteur SoftDeleteable horodate la ligne et le filtre la masque partout.
 */
final class Version20260915140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute team.deleted_at (suppression douce des équipes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP deleted_at');
    }
}
