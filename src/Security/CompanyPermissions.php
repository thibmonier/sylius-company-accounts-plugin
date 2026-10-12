<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Security;

/**
 * Security attributes of the shop (customers). Every check goes through the voters:
 * `is_granted('COMPANY_VIEW', company)`, `is_granted('ORDER_VIEW_COMPANY', order)`...
 */
final class CompanyPermissions
{
    /** Subject: company. An enabled member of the enabled company. */
    public const COMPANY_VIEW = 'COMPANY_VIEW';

    /** Subject: company. COMPANY_VIEW and the admin role. */
    public const COMPANY_MANAGE_MEMBERS = 'COMPANY_MANAGE_MEMBERS';

    /** Subject: order. The order of the signed-in customer. */
    public const ORDER_VIEW_OWN = 'ORDER_VIEW_OWN';

    /**
     * Subject: order (an order placed for the company of the signed-in member) or company (the
     * company orders list). COMPANY_VIEW and a role listed in `company_orders_roles`.
     */
    public const ORDER_VIEW_COMPANY = 'ORDER_VIEW_COMPANY';

    public const ALL = [self::COMPANY_VIEW, self::COMPANY_MANAGE_MEMBERS, self::ORDER_VIEW_OWN, self::ORDER_VIEW_COMPANY];

    private function __construct()
    {
    }
}
