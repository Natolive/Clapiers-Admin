<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_user_team join table (direct user↔team relation), seeded from the linked member team';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user_team (app_user_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY (app_user_id, team_id))');
        $this->addSql('CREATE INDEX IDX_160EB5E34A3353D8 ON app_user_team (app_user_id)');
        $this->addSql('CREATE INDEX IDX_160EB5E3296CD8AE ON app_user_team (team_id)');
        $this->addSql('ALTER TABLE app_user_team ADD CONSTRAINT FK_160EB5E34A3353D8 FOREIGN KEY (app_user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE app_user_team ADD CONSTRAINT FK_160EB5E3296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Reprise : chaque user lié à un licencié hérite de l'équipe de celui-ci
        $this->addSql(<<<'SQL'
            INSERT INTO app_user_team (app_user_id, team_id)
            SELECT u.id, m.team_id
            FROM app_user u
            JOIN member m ON m.id = u.member_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user_team');
    }
}
