<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** @implements ProviderInterface<CompanyView> */
final class CompanyProvider implements ProviderInterface
{
    public function __construct(
        private readonly ShopApiAccess $access,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CompanyView
    {
        [$member, $company] = $this->access->membership(CompanyPermissions::COMPANY_VIEW);

        return new CompanyView(
            (string) $company->getCode(),
            (string) $company->getName(),
            $company->getVatNumber(),
            $company->getRegistrationNumber(),
            $member->getRole(),
            count($this->memberRepository->findByCompany($company)),
            $this->access->isGranted(CompanyPermissions::COMPANY_MANAGE_MEMBERS, $company),
            $this->access->isGranted(CompanyPermissions::ORDER_VIEW_COMPANY, $company),
        );
    }
}
