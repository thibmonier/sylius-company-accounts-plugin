<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Bridge\OrderHold;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibility;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusOrderHoldPlugin\AccountOwner\AccountOwner;
use Tmonier\SyliusOrderHoldPlugin\AccountOwner\AccountOwnerResolverInterface;

/**
 * Decorates `tmonier_order_hold.resolver.account_owner` (registered only when
 * tmonier/sylius-order-hold-plugin is installed): the company is accountable for the orders
 * of its members, e.g. one trade credit line shared by the company.
 *
 * - A customer: their company when the membership and the company are enabled.
 * - A placed order: the company recorded at checkout (even if the member left since), else the
 *   decorated resolver (orders placed before joining stay the customer's).
 * - A cart, or an order completing in the current flush: the company of its customer, when the
 *   customer is signed in (never for guest orders, see CompanyOrderEligibility).
 */
final class CompanyAccountOwnerResolver implements AccountOwnerResolverInterface
{
    public const TYPE_COMPANY = 'company';

    public function __construct(
        private readonly AccountOwnerResolverInterface $decorated,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveForOrder(OrderInterface $order): ?AccountOwner
    {
        $company = $this->companyOrderRepository->findOneByOrder($order)?->getCompany();
        if (null !== $company) {
            return self::owner($company);
        }

        $customer = $order->getCustomer();
        if ($this->isPlaced($order) || !$customer instanceof CustomerInterface || !CompanyOrderEligibility::isPlacedByAccountHolder($order)) {
            return $this->decorated->resolveForOrder($order);
        }

        return $this->resolveForCustomer($customer);
    }

    public function resolveForCustomer(CustomerInterface $customer): ?AccountOwner
    {
        $member = $this->memberRepository->findOneByCustomer($customer);
        $company = $member?->getCompany();
        if (null === $member || null === $company || !$member->isActive()) {
            return $this->decorated->resolveForCustomer($customer);
        }

        return self::owner($company) ?? $this->decorated->resolveForCustomer($customer);
    }

    /** Checkout completed before the current flush (the company link is then already recorded, or never will be). */
    private function isPlaced(OrderInterface $order): bool
    {
        if (OrderCheckoutStates::STATE_COMPLETED !== $order->getCheckoutState() || null === $order->getId()) {
            return false;
        }
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($order);

        return ($original['checkoutState'] ?? OrderCheckoutStates::STATE_COMPLETED) === OrderCheckoutStates::STATE_COMPLETED;
    }

    private static function owner(CompanyInterface $company): ?AccountOwner
    {
        $id = $company->getId();

        return null === $id ? null : new AccountOwner(self::TYPE_COMPANY, (string) $id, $company);
    }
}
