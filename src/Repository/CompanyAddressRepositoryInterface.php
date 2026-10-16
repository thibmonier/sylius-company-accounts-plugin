<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * @extends RepositoryInterface<CompanyAddressInterface>
 */
interface CompanyAddressRepositoryInterface extends RepositoryInterface
{
    /** @return list<CompanyAddressInterface> sorted by label */
    public function findByCompany(CompanyInterface $company): array;

    /** An address of another company is never found. */
    public function findOneByIdAndCompany(int|string $id, CompanyInterface $company): ?CompanyAddressInterface;
}
