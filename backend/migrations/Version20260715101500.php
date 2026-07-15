<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme le slot médiathèque « Photo de profil » (systemKey profile_picture)
 * en « Photo d'identité » (systemKey identity_photo) sur les lignes existantes.
 */
final class Version20260715101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Slot média : profile_picture → identity_photo (Photo d'identité)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE member_document SET name = 'Photo d''identité' WHERE system_key = 'profile_picture' AND name = 'Photo de profil'");
        $this->addSql("UPDATE member_document SET system_key = 'identity_photo' WHERE system_key = 'profile_picture'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE member_document SET name = 'Photo de profil' WHERE system_key = 'identity_photo' AND name = 'Photo d''identité'");
        $this->addSql("UPDATE member_document SET system_key = 'profile_picture' WHERE system_key = 'identity_photo'");
    }
}
