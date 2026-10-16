<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive: a new table, no existing table or column is touched. Schema API (not raw SQL) so that
 * it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261016100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the company address book table (tmonier/sylius-company-accounts-plugin).';
    }

    public function up(Schema $schema): void
    {
        $address = $schema->createTable('tmonier_company_address');
        $address->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $address->addColumn('company_id', Types::INTEGER);
        $address->addColumn('address_id', Types::INTEGER);
        $address->addColumn('label', Types::STRING, ['length' => 255]);
        $address->addColumn('created_at', Types::DATETIME_MUTABLE);
        $address->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $address->setPrimaryKey(['id']);
        $address->addUniqueIndex(['address_id'], 'UNIQ_67700387F5B7AF75');
        $address->addIndex(['company_id'], 'IDX_67700387979B1AD6');
        $address->addForeignKeyConstraint('sylius_address', ['address_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_67700387F5B7AF75');
        $address->addForeignKeyConstraint('tmonier_company', ['company_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_67700387979B1AD6');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_company_address');
    }
}
