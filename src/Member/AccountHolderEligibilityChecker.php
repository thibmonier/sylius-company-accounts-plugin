<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Orders of a customer signed in to their account. Never guest orders: Sylius attaches an
 * existing customer to a guest order from the e-mail typed at checkout, so a guest order with
 * the e-mail of a member must not become a company order (nor use the company credit).
 */
final class AccountHolderEligibilityChecker implements CompanyOrderEligibilityCheckerInterface
{
    public function isEligible(OrderInterface $order): bool
    {
        $customer = $order->getCustomer();

        return !$order->isCreatedByGuest() && $customer instanceof CustomerInterface && $customer->getUser() instanceof \Sylius\Component\User\Model\UserInterface;
    }
}
