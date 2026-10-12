<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use Sylius\Component\Customer\Model\CustomerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

/**
 * Erasure of a customer: the membership goes with it (the database cascade would do it too,
 * this makes it a `member.removed` event and keeps the unit of work consistent).
 */
final class CustomerRemovalListener
{
    public function __construct(private readonly CompanyMemberRepositoryInterface $memberRepository)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $customer = $args->getObject();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $member = $this->memberRepository->findOneByCustomer($customer);
        if (null !== $member) {
            $member->getCompany()?->removeMember($member);
            $args->getObjectManager()->remove($member);
        }
    }
}
