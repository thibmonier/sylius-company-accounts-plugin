<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * Rules of the member changes made by a company admin in the shop (account pages and shop API).
 * Administrators of the store are not bound by them.
 */
final class MemberChangeGuard
{
    public const ERROR_NOT_ALLOWED = 'tmonier_company_accounts.member.not_allowed';

    public const ERROR_INVALID_ROLE = 'tmonier_company_accounts.member.invalid_role';

    public const ERROR_SELF = 'tmonier_company_accounts.member.self_change';

    public const ERROR_LAST_ADMIN = 'tmonier_company_accounts.member.last_admin';

    /**
     * @param int|null $enabledAdmins enabled admins of the company, counted under a lock by
     *                                MemberUpdater; the loaded members by default
     *
     * @return string|null the translation key (domain `validators`) of the refusal, null when
     *                     the change is allowed
     */
    public function check(CompanyMemberInterface $actor, CompanyMemberInterface $target, string $role, bool $enabled, ?int $enabledAdmins = null): ?string
    {
        $company = $actor->getCompany();
        if (!$actor->isActive() || !$actor->isAdmin() || !$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || $target->getCompany() !== $company) {
            return self::ERROR_NOT_ALLOWED;
        }
        if (!in_array($role, CompanyMemberInterface::ROLES, true)) {
            return self::ERROR_INVALID_ROLE;
        }
        if ($role === $target->getRole() && $enabled === $target->isEnabled()) {
            return null;
        }
        // Nobody demotes or disables themselves out of the company area.
        if ($actor === $target || ($actor->getId() !== null && $actor->getId() === $target->getId())) {
            return self::ERROR_SELF;
        }
        $staysAdmin = CompanyMemberInterface::ROLE_ADMIN === $role && $enabled;
        if ($target->isAdmin() && $target->isEnabled() && !$staysAdmin && ($enabledAdmins ?? count($company->getEnabledAdmins())) <= 1) {
            return self::ERROR_LAST_ADMIN;
        }

        return null;
    }
}
