<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ROLE_ADMIN devient ROLE_COACH : ce rôle n'a jamais désigné qu'un chef
 * d'équipe, le vrai administrateur étant ROLE_SUPER_ADMIN.
 */
final class Version20261030110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme le rôle ROLE_ADMIN en ROLE_COACH';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_user SET roles = REPLACE(roles::text, '"ROLE_ADMIN"', '"ROLE_COACH"')::json
            WHERE roles::text LIKE '%"ROLE_ADMIN"%'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_user SET roles = REPLACE(roles::text, '"ROLE_COACH"', '"ROLE_ADMIN"')::json
            WHERE roles::text LIKE '%"ROLE_COACH"%'
            SQL);
    }
}
