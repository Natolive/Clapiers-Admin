<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un seul numéro de licence FSGT, porté par le membre.
 *
 * `license.license_number` doublonnait `member.license_number` : la fiche
 * membre montrait l'un, la colonne FSGT de la liste l'autre, et les deux
 * divergeaient dès qu'on éditait la fiche. Le numéro est attribué à la
 * personne et la suit d'une saison à l'autre — il n'a rien à faire sur la
 * licence, dont la part saisonnière est déjà `fsgt_registered_at`.
 */
final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime license.license_number : le numéro FSGT vit sur le membre';
    }

    public function up(Schema $schema): void
    {
        // Rapatrie le numéro le plus récent avant de perdre la colonne, pour
        // les fiches (créées à la main) que seule la licence renseignait.
        $this->addSql(<<<'SQL'
            UPDATE member m SET license_number = sub.license_number
            FROM (
                SELECT DISTINCT ON (member_id) member_id, license_number
                FROM license
                WHERE license_number IS NOT NULL
                ORDER BY member_id, season DESC, id DESC
            ) sub
            WHERE sub.member_id = m.id AND m.license_number IS NULL
            SQL);
        $this->addSql('ALTER TABLE license DROP license_number');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license ADD license_number VARCHAR(50) DEFAULT NULL');
        $this->addSql('UPDATE license l SET license_number = m.license_number FROM member m WHERE m.id = l.member_id');
    }
}
