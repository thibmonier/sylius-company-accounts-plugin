<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Twig;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `tmonier_company_addresses()`: the shared addresses offered to the signed-in member. */
final class CompanyAddressesExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('tmonier_company_addresses', $this->addresses(...))];
    }

    /** @return list<CompanyAddressInterface> empty for a visitor, a customer without company or a disabled member */
    public function addresses(): array
    {
        $company = $this->currentMemberResolver->getMember()?->getCompany();
        if (!$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || !$this->authorizationChecker->isGranted(CompanyPermissions::COMPANY_VIEW, $company)) {
            return [];
        }

        return $this->addressRepository->findByCompany($company);
    }
}
