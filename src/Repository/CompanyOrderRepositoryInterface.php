<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;

/**
 * @extends RepositoryInterface<CompanyOrderInterface>
 */
interface CompanyOrderRepositoryInterface extends RepositoryInterface
{
    public function findOneByOrder(OrderInterface $order): ?CompanyOrderInterface;

    /** Orders (alias `o`) of the company, newest first. */
    public function createOrdersQueryBuilder(CompanyInterface $company): QueryBuilder;

    public function findOrderByCompanyAndNumber(CompanyInterface $company, string $number): ?OrderInterface;
}
