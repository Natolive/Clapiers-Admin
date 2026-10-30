<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Invitation d'un licencié comme chef d'équipe : lien à usage unique pour
 * choisir son mot de passe.
 */
final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute le jeton d'invitation sur app_user";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD invitation_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD invitation_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E933FC351A ON app_user (invitation_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_88BDF3E933FC351A');
        $this->addSql('ALTER TABLE app_user DROP invitation_token');
        $this->addSql('ALTER TABLE app_user DROP invitation_expires_at');
    }
}
