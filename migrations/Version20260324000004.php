<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inventory_items table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_items (
            id                  INT AUTO_INCREMENT NOT NULL,
            uuid                VARCHAR(36) NOT NULL,
            product_id          INT NOT NULL,
            product_uuid        VARCHAR(100) NOT NULL,
            product_sku         VARCHAR(100) NOT NULL,
            product_name        VARCHAR(200) NOT NULL,
            quantity_on_hand    INT NOT NULL DEFAULT 0,
            quantity_reserved   INT NOT NULL DEFAULT 0,
            low_stock_threshold INT NOT NULL DEFAULT 10,
            version             INT NOT NULL DEFAULT 1,
            updated_at          DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_6BCFD43AD17F50A6 (uuid),
            UNIQUE INDEX UNIQ_6BCFD43A4584665A (product_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_items');
    }
}
