<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920132426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, type VARCHAR(50) NOT NULL, channel VARCHAR(20) NOT NULL, recipient_phone VARCHAR(50) NOT NULL, message_body TEXT NOT NULL, status VARCHAR(30) NOT NULL, provider VARCHAR(50) NOT NULL, provider_message_id VARCHAR(100) DEFAULT NULL, cost NUMERIC(6, 4) DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, garage_id UUID NOT NULL, customer_id UUID NOT NULL, vehicle_id UUID DEFAULT NULL, work_order_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notification_garage_created_at ON notification (garage_id, created_at)');
        $this->addSql('CREATE INDEX idx_notification_garage_type ON notification (garage_id, type)');
        $this->addSql('CREATE INDEX idx_notification_customer_created_at ON notification (customer_id, created_at)');
        $this->addSql('CREATE INDEX idx_notification_work_order ON notification (work_order_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CAC4FFF555 ON notification (garage_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA9395C3F3 ON notification (customer_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA545317D1 ON notification (vehicle_id)');
        $this->addSql('CREATE TABLE sms_template (id UUID NOT NULL, type VARCHAR(50) NOT NULL, channel VARCHAR(20) NOT NULL, title VARCHAR(100) NOT NULL, body TEXT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, garage_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_sms_template_garage_type ON sms_template (garage_id, type)');
        $this->addSql('CREATE INDEX idx_sms_template_garage_is_active ON sms_template (garage_id, is_active)');
        $this->addSql('CREATE INDEX IDX_F1963E82C4FFF555 ON sms_template (garage_id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAC4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sms_template ADD CONSTRAINT FK_F1963E82C4FFF555 FOREIGN KEY (garage_id) REFERENCES garage (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAC4FFF555');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA9395C3F3');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA545317D1');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA582AE764');
        $this->addSql('ALTER TABLE sms_template DROP CONSTRAINT FK_F1963E82C4FFF555');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE sms_template');
    }
}
