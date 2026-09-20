<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920125157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(150) NOT NULL, phone VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, garage_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_user_garage_id ON app_user (garage_id)');
        $this->addSql('CREATE INDEX idx_user_garage_is_active ON app_user (garage_id, is_active)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9C4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT FK_88BDF3E9C4FFF555');
        $this->addSql('DROP TABLE app_user');
    }
}
