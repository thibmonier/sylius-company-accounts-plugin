<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Factory;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * @extends FactoryInterface<CompanyMemberInterface>
 */
interface CompanyMemberFactoryInterface extends FactoryInterface
{
    /** For the admin "Attach a customer" page: 404 for an unknown company. */
    public function createForCompany(int|string $companyId): CompanyMemberInterface;

    public function createForCustomer(CompanyInterface $company, CustomerInterface $customer, string $role = CompanyMemberInterface::ROLE_BUYER): CompanyMemberInterface;
}
