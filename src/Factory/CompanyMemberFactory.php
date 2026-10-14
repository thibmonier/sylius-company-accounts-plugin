<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Factory;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;

final readonly class CompanyMemberFactory implements CompanyMemberFactoryInterface
{
    /** @param FactoryInterface<CompanyMemberInterface> $decorated */
    public function __construct(
        private FactoryInterface $decorated,
        private CompanyRepositoryInterface $companyRepository,
    ) {
    }

    public function createNew(): CompanyMemberInterface
    {
        return $this->decorated->createNew();
    }

    public function createForCompany(int|string $companyId): CompanyMemberInterface
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof CompanyInterface) {
            throw new NotFoundHttpException('Company not found.');
        }

        $member = $this->createNew();
        $member->setCompany($company);

        return $member;
    }

    public function createForCustomer(CompanyInterface $company, CustomerInterface $customer, string $role = CompanyMemberInterface::ROLE_BUYER): CompanyMemberInterface
    {
        $member = $this->createNew();
        $member->setCustomer($customer);
        $member->setRole($role);
        $member->setCompany($company);

        return $member;
    }
}
