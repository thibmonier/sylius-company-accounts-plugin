<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibility;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;

/**
 * Records the company of an order in the flush that completes its checkout (shop, API, admin
 * tools or fixtures alike), when it is placed by an active member signed in to their account.
 */
final class CompanyOrderListener
{
    /** @param FactoryInterface<CompanyOrderInterface> $companyOrderFactory */
    public function __construct(
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly FactoryInterface $companyOrderFactory,
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
            $change = $entity instanceof OrderInterface ? ($unitOfWork->getEntityChangeSet($entity)['checkoutState'] ?? null) : null;
            if (null !== $change && OrderCheckoutStates::STATE_COMPLETED === $change[1] && $entity instanceof OrderInterface && null === $this->companyOrderRepository->findOneByOrder($entity)) {
                $orders[] = $entity;
            }
        }

        foreach ($orders as $order) {
            $customer = $order->getCustomer();
            if (!CompanyOrderEligibility::isPlacedByAccountHolder($order) || null === $customer) {
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
