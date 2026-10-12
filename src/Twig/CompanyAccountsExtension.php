<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Twig;

use Sylius\Component\Customer\Model\CustomerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CompanyAccountsExtension extends AbstractExtension
{
    public function __construct(
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_company_member', $this->memberOf(...)),
            new TwigFunction('tmonier_company_current_member', $this->currentMember(...)),
        ];
    }

    public function memberOf(mixed $customer): ?CompanyMemberInterface
    {
        return $customer instanceof CustomerInterface ? $this->memberRepository->findOneByCustomer($customer) : null;
    }

    public function currentMember(): ?CompanyMemberInterface
    {
        return $this->currentMemberResolver->getMember();
    }
}
