<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Security;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;

/**
 * Order-level permissions of a signed-in shop user: their own orders, and the orders placed for
 * their company (recorded at checkout, see CompanyOrderInterface) when their role may see them.
 *
 * @extends Voter<string, OrderInterface>
 */
final class OrderVoter extends Voter
{
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly CompanyVoter $companyVoter,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof OrderInterface && in_array($attribute, [CompanyPermissions::ORDER_VIEW_OWN, CompanyPermissions::ORDER_VIEW_COMPANY], true);
    }

    /** @param OrderInterface $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        if (CompanyPermissions::ORDER_VIEW_OWN === $attribute) {
            $customer = $this->currentMemberResolver->getCustomer($token);
            $owner = $subject->getCustomer();

            return null !== $customer && null !== $customer->getId() && null !== $owner && $owner->getId() === $customer->getId();
        }

        $company = $this->companyOrderRepository->findOneByOrder($subject)?->getCompany();

        return null !== $company && self::ACCESS_GRANTED === $this->companyVoter->vote($token, $company, [CompanyPermissions::ORDER_VIEW_COMPANY]);
    }
}
