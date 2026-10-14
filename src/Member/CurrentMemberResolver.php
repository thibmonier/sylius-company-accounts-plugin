<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

final readonly class CurrentMemberResolver implements CurrentMemberResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CompanyMemberRepositoryInterface $memberRepository,
    ) {
    }

    public function getCustomer(?TokenInterface $token = null): ?CustomerInterface
    {
        $user = ($token ?? $this->tokenStorage->getToken())?->getUser();
        if (!$user instanceof ShopUserInterface) {
            return null;
        }
        $customer = $user->getCustomer();

        return $customer instanceof CustomerInterface ? $customer : null;
    }

    public function getMember(?TokenInterface $token = null): ?CompanyMemberInterface
    {
        $customer = $this->getCustomer($token);

        return $customer instanceof \Sylius\Component\Core\Model\CustomerInterface ? $this->memberRepository->findOneByCustomer($customer) : null;
    }
}
