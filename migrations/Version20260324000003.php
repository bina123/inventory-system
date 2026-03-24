<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE products (
            id            INT AUTO_INCREMENT NOT NULL,
            uuid          VARCHAR(36) NOT NULL,
            category_id   INT NOT NULL,
            name          VARCHAR(200) NOT NULL,
            sku           VARCHAR(100) NOT NULL,
            description   LONGTEXT DEFAULT NULL,
            price_amount  INT NOT NULL,
            price_currency VARCHAR(3) NOT NULL,
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at    DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_B3BA5A5AD17F50A6 (uuid),
            UNIQUE INDEX UNIQ_B3BA5A5AF9038C4 (sku),
            INDEX IDX_B3BA5A5A12469DE2 (category_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE products');
    }
}
