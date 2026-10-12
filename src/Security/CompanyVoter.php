<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;

/**
 * Company-level permissions of a signed-in shop user. Disabled members, members of a disabled
 * company, members of another company, administrators and visitors are denied.
 *
 * @extends Voter<string, CompanyInterface>
 */
final class CompanyVoter extends Voter
{
    /** @param list<string> $companyOrdersRoles */
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly array $companyOrdersRoles,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof CompanyInterface && in_array($attribute, [
            CompanyPermissions::COMPANY_VIEW,
            CompanyPermissions::COMPANY_MANAGE_MEMBERS,
            CompanyPermissions::ORDER_VIEW_COMPANY,
        ], true);
    }

    /** @param CompanyInterface $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        $member = $this->currentMemberResolver->getMember($token);
        $company = $member?->getCompany();
        if (null === $member || null === $company || !$member->isActive() || null === $subject->getId() || $company->getId() !== $subject->getId()) {
            return false;
        }

        return match ($attribute) {
            CompanyPermissions::COMPANY_VIEW => true,
            CompanyPermissions::COMPANY_MANAGE_MEMBERS => $member->isAdmin(),
            CompanyPermissions::ORDER_VIEW_COMPANY => in_array($member->getRole(), $this->companyOrdersRoles, true),
            default => false,
        };
    }
}
