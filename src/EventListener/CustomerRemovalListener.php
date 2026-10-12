<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

/**
 * Erasure: the membership goes with the customer (the database cascade would do it too, this
 * makes it a `member.removed` event and keeps the unit of work consistent) and with the shop
 * account. Sylius 2 has no customer deletion in the back office: its "Delete" action on the
 * customer page deletes the shop user, after which the person can no longer use the company
 * area and must not stay listed (or counted as an admin) among their colleagues.
 */
final class CustomerRemovalListener
{
    public function __construct(private readonly CompanyMemberRepositoryInterface $memberRepository)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();
        $customer = match (true) {
            $object instanceof CustomerInterface => $object,
            $object instanceof ShopUserInterface => $object->getCustomer(),
            default => null,
        };
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $member = $this->memberRepository->findOneByCustomer($customer);
        if (null !== $member && !$args->getObjectManager()->getUnitOfWork()->isScheduledForDelete($member)) {
            $member->getCompany()?->removeMember($member);
            $args->getObjectManager()->remove($member);
        }
    }
}
