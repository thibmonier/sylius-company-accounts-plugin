<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * @extends EntityRepository<CompanyInterface>
 */
class CompanyRepository extends EntityRepository implements CompanyRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('defaultCustomerGroup')
            ->leftJoin('o.defaultCustomerGroup', 'defaultCustomerGroup')
        ;
    }

    public function findOneByCode(string $code): ?CompanyInterface
    {
        $company = $this->findOneBy(['code' => $code]);

        return $company instanceof CompanyInterface ? $company : null;
    }
}
