<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605131215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ROLE_CONFIRM_MESSAGE from existing user roles (role deleted with read/unread system)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_user
            SET roles = COALESCE(
                (
                    SELECT jsonb_agg(elem)
                    FROM jsonb_array_elements_text(roles::jsonb) AS elem
                    WHERE elem <> 'ROLE_CONFIRM_MESSAGE'
                ),
                '[]'::jsonb
            )::json
            WHERE roles::jsonb @> '["ROLE_CONFIRM_MESSAGE"]'::jsonb
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible data cleanup: we cannot know which users previously held ROLE_CONFIRM_MESSAGE
    }
}
