<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * @extends RepositoryInterface<CompanyMemberInterface>
 */
interface CompanyMemberRepositoryInterface extends RepositoryInterface
{
    /** The membership of the customer, enabled or not (a customer belongs to one company at most). */
    public function findOneByCustomer(CustomerInterface $customer): ?CompanyMemberInterface;

    /** Admin grid of the members of a company. */
    public function createListQueryBuilderByCompanyId(int|string $companyId): QueryBuilder;

    /** @return list<CompanyMemberInterface> members of the company by last name, first name */
    public function findByCompany(CompanyInterface $company): array;

    public function findOneByIdAndCompany(int|string $id, CompanyInterface $company): ?CompanyMemberInterface;

    public function findOneByIdAndCompanyId(int|string $id, int|string $companyId): ?CompanyMemberInterface;
}
