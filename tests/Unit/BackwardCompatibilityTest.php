<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/**
 * 1.x promise: the interfaces that applications implement or decorate (entities, repositories)
 * do not get a new method in a minor version, and the existing permissions keep their value.
 * Address book features (1.2) live in new interfaces.
 */
final class BackwardCompatibilityTest extends TestCase
{
    public function testTheEntityAndRepositoryInterfacesOf11AreUnchanged(): void
    {
        $this->assertMethods(CompanyInterface::class, ['getId', 'getName', 'setName', 'getVatNumber', 'setVatNumber', 'getRegistrationNumber', 'setRegistrationNumber', 'getDefaultCustomerGroup', 'setDefaultCustomerGroup', 'getMembers', 'hasMember', 'addMember', 'removeMember', 'getEnabledAdmins']);
        $this->assertMethods(CompanyMemberInterface::class, ['getId', 'getCompany', 'setCompany', 'getCustomer', 'setCustomer', 'getRole', 'setRole', 'isAdmin', 'isCustomerGroupAssigned', 'setCustomerGroupAssigned', 'isActive']);
        $this->assertMethods(CompanyOrderInterface::class, ['getId', 'getCompany', 'setCompany', 'getOrder', 'setOrder', 'getCreatedAt']);
        $this->assertMethods(CompanyRepositoryInterface::class, ['createListQueryBuilder', 'findOneByCode']);
        $this->assertMethods(CompanyMemberRepositoryInterface::class, ['findOneByCustomer', 'createListQueryBuilderByCompanyId', 'findByCompany', 'findOneByIdAndCompany', 'countEnabledAdmins', 'findIdsInCompany', 'findOneByIdAndCompanyId']);
        $this->assertMethods(CompanyOrderRepositoryInterface::class, ['findOneByOrder', 'createOrdersQueryBuilder', 'findOrderByCompanyAndNumber']);
    }

    public function testThePermissionsOf11KeepTheirValue(): void
    {
        self::assertSame('COMPANY_VIEW', CompanyPermissions::COMPANY_VIEW);
        self::assertSame('COMPANY_MANAGE_MEMBERS', CompanyPermissions::COMPANY_MANAGE_MEMBERS);
        self::assertSame('ORDER_VIEW_OWN', CompanyPermissions::ORDER_VIEW_OWN);
        self::assertSame('ORDER_VIEW_COMPANY', CompanyPermissions::ORDER_VIEW_COMPANY);
        self::assertSame(['COMPANY_VIEW', 'COMPANY_MANAGE_MEMBERS', 'ORDER_VIEW_OWN', 'ORDER_VIEW_COMPANY'], array_slice(CompanyPermissions::ALL, 0, 4));
    }

    /**
     * @param class-string $interface
     * @param list<string> $own methods declared by the interface itself in 1.1
     */
    private function assertMethods(string $interface, array $own): void
    {
        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter((new \ReflectionClass($interface))->getMethods(), static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $interface),
        );
        sort($declared);
        sort($own);

        self::assertSame($own, $declared, $interface);
    }
}
