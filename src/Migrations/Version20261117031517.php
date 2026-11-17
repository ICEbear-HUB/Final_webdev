<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117031517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, movement_type VARCHAR(20) NOT NULL, quantity INT NOT NULL, previous_stock INT NOT NULL, new_stock INT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, performed_by_id INT DEFAULT NULL, INDEX IDX_BB1BC1B54584665A (product_id), INDEX IDX_BB1BC1B52E65C292 (performed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B54584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B52E65C292 FOREIGN KEY (performed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B54584665A');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B52E65C292');
        $this->addSql('DROP TABLE stock_movement');
    }
}
