<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920075243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vehicle (id UUID NOT NULL, license_plate VARCHAR(20) NOT NULL, vin VARCHAR(17) DEFAULT NULL, make VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL, year INT DEFAULT NULL, engine_code VARCHAR(50) DEFAULT NULL, engine_displacement INT DEFAULT NULL, engine_power_hp INT DEFAULT NULL, fuel_type VARCHAR(50) DEFAULT NULL, transmission VARCHAR(50) DEFAULT NULL, color VARCHAR(50) DEFAULT NULL, first_registration_date DATE DEFAULT NULL, mileage INT DEFAULT NULL, last_service_date DATE DEFAULT NULL, last_service_mileage INT DEFAULT NULL, next_kteo_date DATE DEFAULT NULL, next_service_date DATE DEFAULT NULL, next_service_mileage INT DEFAULT NULL, last_kteo_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_service_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, allow_reminders BOOLEAN NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, garage_id UUID NOT NULL, customer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_vehicle_garage_license_plate ON vehicle (garage_id, license_plate)');
        $this->addSql('CREATE INDEX idx_vehicle_garage_vin ON vehicle (garage_id, vin)');
        $this->addSql('CREATE INDEX idx_vehicle_garage_next_kteo_date ON vehicle (garage_id, next_kteo_date)');
        $this->addSql('CREATE INDEX idx_vehicle_garage_next_service_date ON vehicle (garage_id, next_service_date)');
        $this->addSql('CREATE INDEX idx_vehicle_customer_id ON vehicle (customer_id)');
        $this->addSql('CREATE INDEX idx_vehicle_garage_created_at ON vehicle (garage_id, created_at)');
        $this->addSql('CREATE INDEX IDX_1B80E486C4FFF555 ON vehicle (garage_id)');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E486C4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4869395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicle DROP CONSTRAINT FK_1B80E486C4FFF555');
        $this->addSql('ALTER TABLE vehicle DROP CONSTRAINT FK_1B80E4869395C3F3');
        $this->addSql('DROP TABLE vehicle');
    }
}
