<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberUpdater;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;

/**
 * Concurrent changes are simulated by writing the other request's change with plain SQL: the
 * loaded entities are then stale, as in a second process.
 */
final class MemberUpdaterTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTwoAdminsDemotingEachOtherKeepOneAdmin(): void
    {
        $company = $this->catalog()->company('Duel');
        $x = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $y = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);

        // X demoted Y in another request, already committed.
        $this->connection()->update('tmonier_company_member', ['role' => 'buyer'], ['id' => $y->getId()]);

        // Y's request, with Y still an admin in memory, demotes X.
        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->updater()->update($y, $x, 'buyer', true));

        $this->entityManager()->clear();
        self::assertSame(1, $this->adminCount((int) $company->getId()));
    }

    public function testTheLastAdminIsCountedInTheDatabase(): void
    {
        $company = $this->catalog()->company('Last');
        $x = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $y = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $z = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);

        // Another request disabled X meanwhile; X's stale request then demotes Y: refused, X
        // is no longer an admin. Y demoting Z still leaves Y.
        $this->connection()->update('tmonier_company_member', ['enabled' => false], ['id' => $x->getId()], ['enabled' => Types::BOOLEAN]);
        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->updater()->update($x, $y, 'buyer', true));
        self::assertNull($this->updater()->update($y, $z, 'buyer', true));
        $this->entityManager()->flush();

        $this->entityManager()->clear();
        self::assertSame(1, $this->adminCount((int) $company->getId()));
        self::assertSame('buyer', $this->reload($z)->getRole());
    }

    public function testAnAllowedChangeIsSaved(): void
    {
        $company = $this->catalog()->company('Saved');
        $admin = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $buyer = $this->catalog()->member($company);

        self::assertNull($this->updater()->update($admin, $buyer, 'approver', false));

        $buyer = $this->reload($buyer);
        self::assertSame(['approver', false], [$buyer->getRole(), $buyer->isEnabled()]);
    }

    private function adminCount(int $companyId): int
    {
        $company = $this->service('tmonier_company_accounts.repository.company', CompanyRepositoryInterface::class)->find($companyId);
        self::assertInstanceOf(CompanyInterface::class, $company);

        return $this->service('tmonier_company_accounts.repository.company_member', CompanyMemberRepositoryInterface::class)->countEnabledAdmins($company);
    }

    private function updater(): MemberUpdater
    {
        return $this->service('tmonier_company_accounts.member_updater', MemberUpdater::class);
    }
}
