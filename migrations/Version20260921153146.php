<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921153146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to app_user and replace strict unique index with partial unique index where deleted_at is null';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_user_email');
        $this->addSql('ALTER TABLE app_user ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_active ON app_user (email) WHERE (deleted_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_user_email_active');
        $this->addSql('ALTER TABLE app_user DROP deleted_at');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
    }
}
