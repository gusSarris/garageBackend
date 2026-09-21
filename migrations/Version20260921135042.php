<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921135042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD invitation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD invitation_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9298D7A97 ON app_user (invitation_token_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_88BDF3E9298D7A97');
        $this->addSql('ALTER TABLE app_user DROP invitation_token_hash');
        $this->addSql('ALTER TABLE app_user DROP invitation_expires_at');
    }
}
