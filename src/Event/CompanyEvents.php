<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Event;

/**
 * Dispatched on the Symfony event dispatcher after the flush that made the change (admin, API,
 * shop, fixtures or your own code), in the order of the unit of work. Listen to them to feed a
 * webhook outbox, a timeline or an audit trail.
 */
final class CompanyEvents
{
    /** CompanyEvent */
    public const COMPANY_CREATED = 'tmonier_company_accounts.company.created';

    /** CompanyEvent, with the changed fields */
    public const COMPANY_UPDATED = 'tmonier_company_accounts.company.updated';

    /** CompanyEvent (the company is detached: rely on the snapshot getters) */
    public const COMPANY_DELETED = 'tmonier_company_accounts.company.deleted';

    /** CompanyMemberEvent */
    public const MEMBER_ADDED = 'tmonier_company_accounts.member.added';

    /** CompanyMemberEvent, previous role in the changes */
    public const MEMBER_ROLE_CHANGED = 'tmonier_company_accounts.member.role_changed';

    /** CompanyMemberEvent, previous status in the changes */
    public const MEMBER_ENABLED_CHANGED = 'tmonier_company_accounts.member.enabled_changed';

    /** CompanyMemberEvent (detached from the company, or its customer was deleted) */
    public const MEMBER_REMOVED = 'tmonier_company_accounts.member.removed';

    public const ALL = [
        self::COMPANY_CREATED,
        self::COMPANY_UPDATED,
        self::COMPANY_DELETED,
        self::MEMBER_ADDED,
        self::MEMBER_ROLE_CHANGED,
        self::MEMBER_ENABLED_CHANGED,
        self::MEMBER_REMOVED,
    ];

    private function __construct()
    {
    }
}
