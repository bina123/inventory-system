<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create categories table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categories (
            id          INT AUTO_INCREMENT NOT NULL,
            uuid        VARCHAR(36) NOT NULL,
            name        VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_3AF34668D17F50A6 (uuid),
            UNIQUE INDEX UNIQ_3AF346685E237E06 (name),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE categories');
    }
}
