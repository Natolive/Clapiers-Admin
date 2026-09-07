<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire les DEFAULT '' résiduels sur les colonnes legal_rep_* de `member`.
 *
 * Dérive de schéma, pas un changement de comportement. Version20260709134522 a
 * ajouté ces quatre colonnes en `NOT NULL DEFAULT ''` : le DEFAULT n'était là
 * que pour remplir les lignes existantes au moment de l'ALTER. Il n'a jamais
 * été retiré ensuite, alors que l'embeddable LegalRepresentative n'en déclare
 * aucun — d'où un `doctrine:migrations:diff` qui reproposait ces quatre lignes
 * à chaque génération, et les faisait embarquer par erreur dans des migrations
 * sans rapport.
 *
 * Sans risque : les colonnes restent NOT NULL, seul l'ORM écrit dans `member`
 * et il fournit toujours les quatre valeurs (l'embeddable les initialise à '').
 */
final class Version20260907122150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Aligne le schéma sur le mapping : retire les DEFAULT '' sur member.legal_rep_*";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ALTER legal_rep_first_name DROP DEFAULT');
        $this->addSql('ALTER TABLE member ALTER legal_rep_last_name DROP DEFAULT');
        $this->addSql('ALTER TABLE member ALTER legal_rep_email DROP DEFAULT');
        $this->addSql('ALTER TABLE member ALTER legal_rep_phone DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE member ALTER legal_rep_first_name SET DEFAULT ''");
        $this->addSql("ALTER TABLE member ALTER legal_rep_last_name SET DEFAULT ''");
        $this->addSql("ALTER TABLE member ALTER legal_rep_email SET DEFAULT ''");
        $this->addSql("ALTER TABLE member ALTER legal_rep_phone SET DEFAULT ''");
    }
}
