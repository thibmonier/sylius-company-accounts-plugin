<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261012400000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the company, company member and company order tables (tmonier/sylius-company-accounts-plugin).';
    }

    public function up(Schema $schema): void
    {
        $company = $schema->createTable('tmonier_company');
        $company->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $company->addColumn('default_customer_group_id', Types::INTEGER, ['notnull' => false]);
        $company->addColumn('code', Types::STRING, ['length' => 64]);
        $company->addColumn('name', Types::STRING, ['length' => 255]);
        $company->addColumn('vat_number', Types::STRING, ['length' => 32, 'notnull' => false]);
        $company->addColumn('registration_number', Types::STRING, ['length' => 64, 'notnull' => false]);
        $company->addColumn('enabled', Types::BOOLEAN);
        $company->addColumn('created_at', Types::DATETIME_MUTABLE);
        $company->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $company->setPrimaryKey(['id']);
        $company->addUniqueIndex(['code'], 'UNIQ_14E348A677153098');
        $company->addIndex(['default_customer_group_id'], 'IDX_14E348A62711A3F7');
        $company->addForeignKeyConstraint('sylius_customer_group', ['default_customer_group_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_14E348A62711A3F7');

        $member = $schema->createTable('tmonier_company_member');
        $member->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $member->addColumn('company_id', Types::INTEGER);
        $member->addColumn('customer_id', Types::INTEGER);
        $member->addColumn('role', Types::STRING, ['length' => 16]);
        $member->addColumn('enabled', Types::BOOLEAN);
        $member->addColumn('created_at', Types::DATETIME_MUTABLE);
        $member->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $member->setPrimaryKey(['id']);
        $member->addUniqueIndex(['customer_id'], 'UNIQ_FEA80E699395C3F3');
        $member->addIndex(['company_id'], 'IDX_FEA80E69979B1AD6');
        $member->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_FEA80E699395C3F3');
        $member->addForeignKeyConstraint('tmonier_company', ['company_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_FEA80E69979B1AD6');

        $order = $schema->createTable('tmonier_company_order');
        $order->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $order->addColumn('company_id', Types::INTEGER);
        $order->addColumn('order_id', Types::INTEGER);
        $order->addColumn('created_at', Types::DATETIME_MUTABLE);
        $order->setPrimaryKey(['id']);
        $order->addUniqueIndex(['order_id'], 'UNIQ_7327B058D9F6D38');
        $order->addIndex(['company_id'], 'IDX_7327B05979B1AD6');
        $order->addForeignKeyConstraint('sylius_order', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7327B058D9F6D38');
        $order->addForeignKeyConstraint('tmonier_company', ['company_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7327B05979B1AD6');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_company_order');
        $schema->dropTable('tmonier_company_member');
        $schema->dropTable('tmonier_company');
    }
}
