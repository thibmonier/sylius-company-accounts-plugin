<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Fixture\CompanyFixture;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;

final class CompanyFixtureTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheDemoCompanyHasThreeVerifiedMembersAndCompanyOrders(): void
    {
        $fixture = $this->service('tmonier_company_accounts.fixture.company', CompanyFixture::class);
        $code = 'DEMO_' . strtoupper($this->catalog()->suffix);
        // Created by the test: the random fixtures may have no shipping method for the channel.
        $this->catalog()->placedOrder($this->catalog()->customer('warm-up'));

        $fixture->load([
            'code' => $code,
            'name' => 'Demo',
            'vat_number' => null,
            'registration_number' => null,
            'customer_group' => null,
            'email_domain' => $this->catalog()->suffix . '.example.com',
            'orders_per_member' => 1,
            'channel' => $this->catalog()->channel()->getCode(),
        ]);

        $company = $this->service('tmonier_company_accounts.repository.company', CompanyRepositoryInterface::class)->findOneByCode($code);
        self::assertInstanceOf(CompanyInterface::class, $company);
        $roles = [];
        foreach ($company->getMembers() as $member) {
            $roles[] = $member->getRole();
            self::assertNotNull($member->getCustomer()?->getUser()?->getVerifiedAt());
        }
        self::assertEqualsCanonicalizing(['admin', 'buyer', 'approver'], $roles);
        $orders = $this->service('tmonier_company_accounts.repository.company_order', CompanyOrderRepositoryInterface::class)->createOrdersQueryBuilder($company)->getQuery()->getResult();
        self::assertIsArray($orders);
        self::assertCount(2, $orders);
    }
}
