<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611141049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Member↔Team en ManyToMany (member_team), seedé depuis member.team_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member_team (member_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY (member_id, team_id))');
        $this->addSql('CREATE INDEX IDX_38688A437597D3FE ON member_team (member_id)');
        $this->addSql('CREATE INDEX IDX_38688A43296CD8AE ON member_team (team_id)');
        $this->addSql('ALTER TABLE member_team ADD CONSTRAINT FK_38688A437597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_team ADD CONSTRAINT FK_38688A43296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');

        // Reprise : chaque licencié garde son équipe actuelle
        $this->addSql('INSERT INTO member_team (member_id, team_id) SELECT id, team_id FROM member');

        $this->addSql('ALTER TABLE member DROP CONSTRAINT fk_70e4fa78296cd8ae');
        $this->addSql('DROP INDEX idx_70e4fa78296cd8ae');
        $this->addSql('ALTER TABLE member DROP team_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_team DROP CONSTRAINT FK_38688A437597D3FE');
        $this->addSql('ALTER TABLE member_team DROP CONSTRAINT FK_38688A43296CD8AE');
        $this->addSql('DROP TABLE member_team');
        $this->addSql('ALTER TABLE member ADD team_id INT NOT NULL');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT fk_70e4fa78296cd8ae FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_70e4fa78296cd8ae ON member (team_id)');
    }
}
