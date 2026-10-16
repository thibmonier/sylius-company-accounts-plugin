<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

class CompanyAddressRepository extends EntityRepository implements CompanyAddressRepositoryInterface
{
    public function findByCompany(CompanyInterface $company): array
    {
        /** @var list<CompanyAddressInterface> $addresses */
        $addresses = $this->createQueryBuilder('o')
            ->addSelect('address')
            ->innerJoin('o.address', 'address')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->addOrderBy('o.label', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $addresses;
    }

    public function findOneByIdAndCompany(int|string $id, CompanyInterface $company): ?CompanyAddressInterface
    {
        $address = $this->findOneBy(['id' => $id, 'company' => $company]);

        return $address instanceof CompanyAddressInterface ? $address : null;
    }
}
