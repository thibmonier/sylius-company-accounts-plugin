<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Customer\Model\CustomerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

class CompanyMemberRepository extends EntityRepository implements CompanyMemberRepositoryInterface
{
    public function findOneByCustomer(CustomerInterface $customer): ?CompanyMemberInterface
    {
        if (null === $customer->getId()) {
            return null;
        }

        $member = $this->createQueryBuilder('o')
            ->addSelect('company')
            ->innerJoin('o.company', 'company')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $member instanceof CompanyMemberInterface ? $member : null;
    }

    public function createListQueryBuilderByCompanyId(int|string $companyId): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('customer')
            ->innerJoin('o.customer', 'customer')
            ->andWhere('o.company = :companyId')
            ->setParameter('companyId', $companyId)
        ;
    }

    public function findByCompany(CompanyInterface $company): array
    {
        /** @var list<CompanyMemberInterface> $members */
        $members = $this->createQueryBuilder('o')
            ->addSelect('customer')
            ->innerJoin('o.customer', 'customer')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->addOrderBy('customer.lastName', 'ASC')
            ->addOrderBy('customer.firstName', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $members;
    }

    public function findOneByIdAndCompany(int|string $id, CompanyInterface $company): ?CompanyMemberInterface
    {
        $member = $this->findOneBy(['id' => $id, 'company' => $company]);

        return $member instanceof CompanyMemberInterface ? $member : null;
    }

    public function countEnabledAdmins(CompanyInterface $company): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.company = :company')
            ->andWhere('o.enabled = true')
            ->andWhere('o.role = :role')
            ->setParameter('company', $company)
            ->setParameter('role', CompanyMemberInterface::ROLE_ADMIN)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findIdsInCompany(CompanyInterface $company, array $ids): array
    {
        /** @var list<int|string> $found */
        $found = $this->createQueryBuilder('o')
            ->select('o.id')
            ->andWhere('o.company = :company')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('company', $company)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return array_map(static fn (int|string $id): int => (int) $id, $found);
    }

    public function findOneByIdAndCompanyId(int|string $id, int|string $companyId): ?CompanyMemberInterface
    {
        $member = $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.company = :companyId')
            ->setParameter('id', $id)
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $member instanceof CompanyMemberInterface ? $member : null;
    }
}
