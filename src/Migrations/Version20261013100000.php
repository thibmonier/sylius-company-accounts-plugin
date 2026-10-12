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

    public function down(Schema $schema): void
    {
        $schema->getTable('tmonier_company_member')->dropColumn('customer_group_assigned');
    }
}
