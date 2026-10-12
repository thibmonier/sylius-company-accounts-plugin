<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember whether the company gave its default customer group to a member (tmonier/sylius-company-accounts-plugin).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('tmonier_company_member')->addColumn('customer_group_assigned', Types::BOOLEAN, ['default' => false]);
    }

    /**
     * Existing members whose customer group is the default group of their company got it from
     * the company (it was given on joining): mark it, so that it is still taken back when they
     * leave. Portable SQL (MySQL, MariaDB, PostgreSQL).
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(self::BACKFILL_SQL);
    }

    public const BACKFILL_SQL = <<<'SQL'
        UPDATE tmonier_company_member SET customer_group_assigned = TRUE
        WHERE EXISTS (
            SELECT 1 FROM tmonier_company company
            INNER JOIN sylius_customer customer ON customer.customer_group_id = company.default_customer_group_id
            WHERE company.id = tmonier_company_member.company_id
            AND customer.id = tmonier_company_member.customer_id
        )
        SQL;

    public function down(Schema $schema): void
    {
        $schema->getTable('tmonier_company_member')->dropColumn('customer_group_assigned');
    }
}
