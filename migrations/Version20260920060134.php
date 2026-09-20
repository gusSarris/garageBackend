<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920060134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer (id UUID NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, vat_number VARCHAR(50) DEFAULT NULL, tax_office VARCHAR(100) DEFAULT NULL, phone VARCHAR(50) NOT NULL, secondary_phone VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, garage_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_customer_garage_phone ON customer (garage_id, phone)');
        $this->addSql('CREATE INDEX idx_customer_garage_last_name ON customer (garage_id, last_name)');
        $this->addSql('CREATE INDEX idx_customer_garage_created_at ON customer (garage_id, created_at)');
        $this->addSql('CREATE INDEX IDX_81398E09C4FFF555 ON customer (garage_id)');
        $this->addSql('CREATE TABLE garage (id UUID NOT NULL, name VARCHAR(255) NOT NULL, vat_number VARCHAR(50) DEFAULT NULL, tax_office VARCHAR(100) DEFAULT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, subscription_status VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09C4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer DROP CONSTRAINT FK_81398E09C4FFF555');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE garage');
    }
}
