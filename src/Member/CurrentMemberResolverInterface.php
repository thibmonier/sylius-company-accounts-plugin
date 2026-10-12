<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

interface CurrentMemberResolverInterface
{
    /** The customer of a shop user token (null for administrators, API clients and visitors). */
    public function getCustomer(?TokenInterface $token = null): ?CustomerInterface;

    /** The membership of the signed-in shop user, enabled or not. */
    public function getMember(?TokenInterface $token = null): ?CompanyMemberInterface;
}
