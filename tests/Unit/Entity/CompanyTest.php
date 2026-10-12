<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\Company;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMember;

final class CompanyTest extends TestCase
{
    public function testMembersKeepBothSidesInSync(): void
    {
        $company = new Company();
        $member = new CompanyMember();
        $member->setCompany($company);
        $company->addMember($member);

        self::assertCount(1, $company->getMembers());
        self::assertSame($company, $member->getCompany());
        self::assertSame('buyer', $member->getRole(), 'Buyer by default.');
        self::assertTrue($member->isActive());

        $company->setEnabled(false);
        self::assertFalse($member->isActive());

        $company->removeMember($member);
        self::assertCount(0, $company->getMembers());
    }

    public function testEnabledAdmins(): void
    {
        $company = new Company();
        foreach ([['admin', true], ['admin', false], ['approver', true], ['admin', true]] as [$role, $enabled]) {
            $member = new CompanyMember();
            $member->setRole($role);
            $member->setEnabled($enabled);
            $company->addMember($member);
        }

        self::assertCount(2, $company->getEnabledAdmins());
    }

    public function testIdentifiersAreTrimmed(): void
    {
        $company = new Company();
        $company->setVatNumber('  FR40123456824 ');
        $company->setRegistrationNumber('   ');

        self::assertSame('FR40123456824', $company->getVatNumber());
        self::assertNull($company->getRegistrationNumber());
        self::assertTrue($company->isEnabled(), 'Enabled by default.');
    }
}
