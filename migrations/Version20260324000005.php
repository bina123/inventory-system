<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_items tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders (
            id             INT AUTO_INCREMENT NOT NULL,
            uuid           VARCHAR(36) NOT NULL,
            customer_email VARCHAR(180) NOT NULL,
            status         VARCHAR(20) NOT NULL DEFAULT \'pending\',
            total_amount   INT NOT NULL DEFAULT 0,
            total_currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            notes          LONGTEXT DEFAULT NULL,
            placed_at      DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at     DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_E52FFDEED17F50A6 (uuid),
            INDEX IDX_E52FFDEE21E8C174 (status),
            INDEX IDX_E52FFDEE4A9E0E43 (customer_email),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE order_items (
            id                  INT AUTO_INCREMENT NOT NULL,
            order_id            INT NOT NULL,
            product_uuid        VARCHAR(100) NOT NULL,
            product_sku         VARCHAR(100) NOT NULL,
            product_name        VARCHAR(200) NOT NULL,
            quantity            INT NOT NULL,
            unit_price_amount   INT NOT NULL,
            unit_price_currency VARCHAR(3) NOT NULL,
            INDEX IDX_62809DB08D9F6D38 (order_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
    }
}
