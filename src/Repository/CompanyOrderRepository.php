<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;

class CompanyOrderRepository extends EntityRepository implements CompanyOrderRepositoryInterface
{
    public function findOneByOrder(OrderInterface $order): ?CompanyOrderInterface
    {
        if (null === $order->getId()) {
            return null;
        }
        $companyOrder = $this->findOneBy(['order' => $order]);

        return $companyOrder instanceof CompanyOrderInterface ? $companyOrder : null;
    }

    public function createOrdersQueryBuilder(CompanyInterface $company): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('o', 'customer')
            ->from(OrderInterface::class, 'o')
            ->innerJoin($this->getClassName(), 'companyOrder', 'WITH', 'companyOrder.order = o')
            ->leftJoin('o.customer', 'customer')
            ->andWhere('companyOrder.company = :company')
            ->setParameter('company', $company)
            ->addOrderBy('o.checkoutCompletedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
        ;
    }

    public function findOrderByCompanyAndNumber(CompanyInterface $company, string $number): ?OrderInterface
    {
        $order = $this->createOrdersQueryBuilder($company)
            ->andWhere('o.number = :number')
            ->setParameter('number', $number)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $order instanceof OrderInterface ? $order : null;
    }
}
