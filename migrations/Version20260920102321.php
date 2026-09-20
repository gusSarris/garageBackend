<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920102321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE work_order (id UUID NOT NULL, date DATE NOT NULL, status VARCHAR(50) NOT NULL, scheduled_time VARCHAR(10) DEFAULT NULL, description TEXT NOT NULL, price NUMERIC(10, 2) NOT NULL, odometer_km INT DEFAULT NULL, notes TEXT DEFAULT NULL, parts_notes TEXT DEFAULT NULL, checked_in_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, picked_up_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, garage_id UUID NOT NULL, customer_id UUID NOT NULL, vehicle_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_work_order_garage_status ON work_order (garage_id, status)');
        $this->addSql('CREATE INDEX idx_work_order_vehicle_date ON work_order (vehicle_id, date)');
        $this->addSql('CREATE INDEX idx_work_order_customer_date ON work_order (customer_id, date)');
        $this->addSql('CREATE INDEX idx_work_order_garage_date ON work_order (garage_id, date)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B7C4FFF555 ON work_order (garage_id)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B79395C3F3 ON work_order (customer_id)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B7545317D1 ON work_order (vehicle_id)');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7C4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B79395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B7C4FFF555');
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B79395C3F3');
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B7545317D1');
        $this->addSql('DROP TABLE work_order');
    }
}
