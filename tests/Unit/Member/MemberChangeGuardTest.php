<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit\Member;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\Company;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMember;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;

final class MemberChangeGuardTest extends TestCase
{
    private MemberChangeGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new MemberChangeGuard();
    }

    public function testAnAdminChangesAColleague(): void
    {
        $company = new Company();
        $admin = self::member($company, 'admin');
        $buyer = self::member($company, 'buyer');

        self::assertNull($this->guard->check($admin, $buyer, 'approver', true));
        self::assertNull($this->guard->check($admin, $buyer, 'admin', false));
        self::assertNull($this->guard->check($admin, $buyer, 'buyer', true), 'No change.');
    }

    public function testOnlyActiveAdminsOfTheSameCompanyChangeMembers(): void
    {
        $company = new Company();
        $buyer = self::member($company, 'buyer');
        $target = self::member($company, 'buyer');
        $disabledAdmin = self::member($company, 'admin', false);
        $foreignAdmin = self::member(new Company(), 'admin');
        $closed = new Company();
        $closed->setEnabled(false);
        $adminOfClosed = self::member($closed, 'admin');
        $targetOfClosed = self::member($closed, 'buyer');

        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->guard->check($buyer, $target, 'admin', true));
        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->guard->check($disabledAdmin, $target, 'admin', true));
        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->guard->check($foreignAdmin, $target, 'admin', true));
        self::assertSame(MemberChangeGuard::ERROR_NOT_ALLOWED, $this->guard->check($adminOfClosed, $targetOfClosed, 'admin', true));
    }

    public function testRolesAreChecked(): void
    {
        $company = new Company();

        self::assertSame(MemberChangeGuard::ERROR_INVALID_ROLE, $this->guard->check(self::member($company, 'admin'), self::member($company, 'buyer'), 'owner', true));
    }

    public function testNobodyLocksThemselvesOut(): void
    {
        $company = new Company();
        $admin = self::member($company, 'admin');
        self::member($company, 'admin');

        self::assertSame(MemberChangeGuard::ERROR_SELF, $this->guard->check($admin, $admin, 'buyer', true));
        self::assertSame(MemberChangeGuard::ERROR_SELF, $this->guard->check($admin, $admin, 'admin', false));
        self::assertNull($this->guard->check($admin, $admin, 'admin', true), 'No change.');
    }

    public function testTheLastEnabledAdminStays(): void
    {
        $company = new Company();
        $actor = self::member($company, 'admin');
        $other = self::member($company, 'admin');
        self::assertNull($this->guard->check($actor, $other, 'buyer', true), 'Two enabled admins.');

        // A stale actor (changed concurrently, no longer counted among the enabled admins): the
        // target is then the last enabled admin.
        $company->removeMember($actor);

        self::assertSame(MemberChangeGuard::ERROR_LAST_ADMIN, $this->guard->check($actor, $other, 'buyer', true));
        self::assertSame(MemberChangeGuard::ERROR_LAST_ADMIN, $this->guard->check($actor, $other, 'admin', false));
        self::assertNull($this->guard->check($actor, $other, 'admin', true), 'No change.');
    }

    private static function member(CompanyInterface $company, string $role, bool $enabled = true): CompanyMemberInterface
    {
        $member = new CompanyMember();
        $member->setRole($role);
        $member->setEnabled($enabled);
        $member->setCompany($company);

        return $member;
    }
}
