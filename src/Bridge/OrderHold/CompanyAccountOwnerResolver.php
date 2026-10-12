<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Bridge\OrderHold;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibilityCheckerInterface;
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
 * - A cart, or an order completing in the current flush (also seen from onFlush and postFlush
 *   listeners): the company of its customer, when the order is eligible
 *   (CompanyOrderEligibilityCheckerInterface: never for guest orders).
 */
final class CompanyAccountOwnerResolver implements AccountOwnerResolverInterface
{
    public const TYPE_COMPANY = 'company';

    public function __construct(
        private readonly AccountOwnerResolverInterface $decorated,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyOrderEligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function resolveForOrder(OrderInterface $order): ?AccountOwner
    {
        $company = $this->recordedCompany($order);
        if (null !== $company) {
            return self::owner($company);
        }

        $customer = $order->getCustomer();
        if ($this->isPlaced($order) || !$customer instanceof CustomerInterface || !$this->eligibilityChecker->isEligible($order)) {
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

    /** The link of the order: in the database, or persisted in the flush running now. */
    private function recordedCompany(OrderInterface $order): ?CompanyInterface
    {
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof CompanyOrderInterface && $entity->getOrder() === $order) {
                return $entity->getCompany();
            }
        }

        return $this->companyOrderRepository->findOneByOrder($order)?->getCompany();
    }

    /**
     * Checkout completed before the current flush (the company link is then already recorded,
     * or never will be). During a flush the unit of work has already copied the new values into
     * the original data: the change set tells an order completing now.
     */
    private function isPlaced(OrderInterface $order): bool
    {
        if (OrderCheckoutStates::STATE_COMPLETED !== $order->getCheckoutState() || null === $order->getId()) {
            return false;
        }
        $unitOfWork = $this->entityManager->getUnitOfWork();
        if ($unitOfWork->isScheduledForInsert($order)) {
            return false;
        }
        $change = $unitOfWork->getEntityChangeSet($order)['checkoutState'] ?? null;
        if (null !== $change && OrderCheckoutStates::STATE_COMPLETED !== $change[0]) {
            return false;
        }
        $original = $unitOfWork->getOriginalEntityData($order);

        return ($original['checkoutState'] ?? OrderCheckoutStates::STATE_COMPLETED) === OrderCheckoutStates::STATE_COMPLETED;
    }

    private static function owner(CompanyInterface $company): ?AccountOwner
    {
        $id = $company->getId();

        return null === $id ? null : new AccountOwner(self::TYPE_COMPANY, (string) $id, $company);
    }
}
