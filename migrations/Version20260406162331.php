<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406162331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_cart (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_7122C47EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_cart_item (id INT AUTO_INCREMENT NOT NULL, user_cart_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, price DOUBLE PRECISION NOT NULL, INDEX IDX_F01EA8C342D8D3B5 (user_cart_id), INDEX IDX_F01EA8C34584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_cart ADD CONSTRAINT FK_7122C47EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_cart_item ADD CONSTRAINT FK_F01EA8C342D8D3B5 FOREIGN KEY (user_cart_id) REFERENCES user_cart (id)');
        $this->addSql('ALTER TABLE user_cart_item ADD CONSTRAINT FK_F01EA8C34584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_cart DROP FOREIGN KEY FK_7122C47EA76ED395');
        $this->addSql('ALTER TABLE user_cart_item DROP FOREIGN KEY FK_F01EA8C342D8D3B5');
        $this->addSql('ALTER TABLE user_cart_item DROP FOREIGN KEY FK_F01EA8C34584665A');
        $this->addSql('DROP TABLE user_cart');
        $this->addSql('DROP TABLE user_cart_item');
    }
}
