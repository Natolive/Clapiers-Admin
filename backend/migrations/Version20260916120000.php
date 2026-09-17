<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscription FSGT par saison : `license.fsgt_registered_at`.
 *
 * On ne pouvait pas le déduire de `license_number` : le formulaire public laisse
 * le demandeur saisir son ancien numéro en cas de renouvellement, donc une
 * licence peut porter un numéro sans que le club ait déclaré qui que ce soit à
 * la fédération pour la saison. La colonne porte la date de déclaration ; null
 * = pas encore inscrit.
 */
final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute license.fsgt_registered_at (inscription FSGT, par saison)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license ADD fsgt_registered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license DROP fsgt_registered_at');
    }
}
