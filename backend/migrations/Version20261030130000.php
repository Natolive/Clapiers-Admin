<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Durcissement de l'authentification :
 * - cache_items : compteurs anti-bruteforce du login (pool cache.rate_limiter,
 *   adaptateur Doctrine DBAL), partagés entre les pods ;
 * - app_user.password_changed_at : un JWT émis avant est refusé.
 */
final class Version20261030130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute cache_items (limitation du login) et app_user.password_changed_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cache_items (item_id VARCHAR(255) NOT NULL, item_data BYTEA NOT NULL, item_lifetime INT DEFAULT NULL, item_time INT NOT NULL, PRIMARY KEY (item_id))');
        $this->addSql('ALTER TABLE app_user ADD password_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cache_items');
        $this->addSql('ALTER TABLE app_user DROP password_changed_at');
    }
}
