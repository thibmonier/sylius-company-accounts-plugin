<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class CompanyOrderEligibility
{
    /**
     * Only orders of a signed-in customer count for their company: Sylius attaches an existing
     * customer to a guest order from the e-mail typed at checkout, so a guest order with the
     * e-mail of a member must not become a company order (nor use the company credit).
     */
    public static function isPlacedByAccountHolder(OrderInterface $order): bool
    {
        $customer = $order->getCustomer();

        return !$order->isCreatedByGuest() && $customer instanceof CustomerInterface && null !== $customer->getUser();
    }

    private function __construct()
    {
    }
}
