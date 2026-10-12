<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibilityCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;

/**
 * Records the company of an order in the flush that completes its checkout (shop, API, admin
 * tools or fixtures alike), when an active member places it (CompanyOrderEligibilityCheckerInterface:
 * from their account, or by staff on their behalf with tmonier/sylius-admin-order-creation-plugin).
 */
final class CompanyOrderListener
{
    /** @param FactoryInterface<CompanyOrderInterface> $companyOrderFactory */
    public function __construct(
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly FactoryInterface $companyOrderFactory,
        private readonly CompanyOrderEligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $orders = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof OrderInterface && OrderCheckoutStates::STATE_COMPLETED === $entity->getCheckoutState()) {
                $orders[] = $entity;
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof OrderInterface) {
                continue;
            }
            $change = $unitOfWork->getEntityChangeSet($entity)['checkoutState'] ?? null;
            if (null !== $change && OrderCheckoutStates::STATE_COMPLETED === $change[1] && null === $this->companyOrderRepository->findOneByOrder($entity)) {
                $orders[] = $entity;
            }
        }

        foreach ($orders as $order) {
            $customer = $order->getCustomer();
            if (null === $customer || !$this->eligibilityChecker->isEligible($order)) {
                continue;
            }
            $member = $this->memberRepository->findOneByCustomer($customer);
            if (null === $member || !$member->isActive()) {
                continue;
            }

            $companyOrder = $this->companyOrderFactory->createNew();
            $companyOrder->setCompany($member->getCompany());
            $companyOrder->setOrder($order);
            $entityManager->persist($companyOrder);
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata($companyOrder::class), $companyOrder);
        }
    }
}
