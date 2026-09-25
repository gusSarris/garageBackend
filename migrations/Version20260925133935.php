<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925133935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE garage ADD settings JSONB DEFAULT \'{}\' NOT NULL');
        $this->addSql('DROP INDEX uniq_active_work_order_per_vehicle');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_work_order_per_vehicle ON work_order (garage_id, vehicle_id) WHERE (status NOT IN (\'delivered\', \'cancelled\') AND picked_up_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE garage DROP settings');
        $this->addSql('DROP INDEX uniq_active_work_order_per_vehicle');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_work_order_per_vehicle ON work_order (garage_id, vehicle_id) WHERE (((status)::text <> ALL ((ARRAY[\'delivered\'::character varying, \'cancelled\'::character varying])::text[])) AND (picked_up_at IS NULL))');
    }
}
