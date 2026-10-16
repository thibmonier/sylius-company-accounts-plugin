<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;

/**
 * The database cascade deletes the address book entries of a deleted company, but not the
 * Sylius addresses they own (personal data): remove them through the ORM instead.
 */
final readonly class CompanyRemovalListener
{
    public function __construct(private CompanyAddressRepositoryInterface $addressRepository)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $company = $args->getObject();
        if (!$company instanceof CompanyInterface || null === $company->getId()) {
            return;
        }

        $entityManager = $args->getObjectManager();
        foreach ($this->addressRepository->findByCompany($company) as $entry) {
            $entityManager->remove($entry);
        }
    }
}
