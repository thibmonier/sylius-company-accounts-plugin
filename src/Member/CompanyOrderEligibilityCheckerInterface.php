<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Tells whether an order (cart or order being placed) is placed on behalf of its customer, so
 * that it may count for the customer's company (company order, company account owner). The
 * membership itself (enabled member of an enabled company) is checked by the caller.
 *
 * Tag `tmonier_company_accounts.company_order_eligibility_checker` (autoconfigured): an order is
 * eligible as soon as one checker accepts it.
 */
interface CompanyOrderEligibilityCheckerInterface
{
    public function isEligible(OrderInterface $order): bool;
}
