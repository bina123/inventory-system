<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id          INT AUTO_INCREMENT NOT NULL,
            uuid        VARCHAR(36) NOT NULL,
            email       VARCHAR(180) NOT NULL,
            roles       JSON NOT NULL,
            password    VARCHAR(255) NOT NULL,
            full_name   VARCHAR(100) NOT NULL,
            created_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_1483A5E9D17F50A6 (uuid),
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
