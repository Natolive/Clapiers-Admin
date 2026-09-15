<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Colonne `member.search_text` : prénom + nom + email + téléphone, mis à plat,
 * en minuscules et sans accents, pour que la recherche des licenciés et celle
 * des demandes de licence trouvent « lea » quand la fiche dit « Léa ».
 *
 * Colonne générée (calculée par Postgres, jamais écrite par l'application) :
 * impossible de la laisser désynchroniser après une modification de fiche.
 * `unaccent()` étant STABLE, une colonne générée et un index ne l'acceptent
 * pas : on passe par `f_unaccent()`, wrapper IMMUTABLE qui fige le dictionnaire
 * utilisé — c'est le motif recommandé par la doc Postgres.
 *
 * Le téléphone est répété sans séparateurs pour qu'une saisie « 06 12 34 »
 * retrouve un numéro stocké « 06.12.34.56.78 », et une troisième fois ramené au
 * format national (33… -> 0…) pour qu'une saisie en 06 retrouve un numéro saisi
 * en +33.
 *
 * L'index GIN trigram sert les `LIKE '%…%'` de ces deux écrans, qui sinon
 * scannent toute la table.
 */
final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recherche licenciés insensible aux accents : colonne générée member.search_text + index trigram';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE PARALLEL SAFE STRICT
            RETURN unaccent('unaccent', $1)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE member ADD COLUMN search_text TEXT
            GENERATED ALWAYS AS (
                f_unaccent(lower(
                    first_name || ' ' || last_name || ' ' || email || ' ' || phone_number
                    || ' ' || regexp_replace(phone_number, '\D', '', 'g')
                    || ' ' || regexp_replace(regexp_replace(phone_number, '\D', '', 'g'), '^33', '0')
                ))
            ) STORED
            SQL);

        $this->addSql('CREATE INDEX idx_member_search_text ON member USING gin (search_text gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_member_search_text');
        $this->addSql('ALTER TABLE member DROP COLUMN search_text');
        $this->addSql('DROP FUNCTION f_unaccent(text)');
    }
}
