<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Bridge\AdminOrderCreation;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusAdminOrderCreationPlugin\Entity\AdminOrderCreationInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibilityCheckerInterface;

/**
 * Registered only when tmonier/sylius-admin-order-creation-plugin is enabled: an order created
 * by staff from the back office (marked by an AdminOrderCreation row) is placed on behalf of
 * its customer, so it counts for the customer's company like an order of the shop.
 */
final class StaffOrderEligibilityChecker implements CompanyOrderEligibilityCheckerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function isEligible(OrderInterface $order): bool
    {
        // Draft created in the flush running now (onFlush listeners).
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof AdminOrderCreationInterface && $entity->getOrder() === $order) {
                return true;
            }
        }
        if (null === $order->getId()) {
            return false;
        }

        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(creation.id)')
            ->from(AdminOrderCreationInterface::class, 'creation')
            ->andWhere('creation.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count > 0;
    }
}
