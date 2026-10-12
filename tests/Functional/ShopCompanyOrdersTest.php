<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class ShopCompanyOrdersTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheCompanyOrdersListOnlyHoldsOrdersPlacedForTheCompany(): void
    {
        $company = $this->catalog()->company('Orders');
        $admin = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $newcomer = $this->catalog()->customer('newcomer', true, 'Nora', 'Newcomer');
        $personalOrder = $this->catalog()->placedOrder($newcomer);
        $member = $this->catalog()->member($company, $newcomer);
        $companyOrder = $this->catalog()->placedOrder($newcomer);
        $guestOrder = $this->catalog()->placedOrder($newcomer, asGuest: true);

        $this->loginShopUser(CompanyScenario::customerOf($admin));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/orders'));

        self::assertResponseIsSuccessful();
        $numbers = $crawler->filter('[data-test-tmonier-company-order]')->each(static fn ($row): string => (string) $row->attr('data-test-tmonier-company-order'));
        self::assertSame([(string) $companyOrder->getNumber()], $numbers);
        self::assertStringContainsString('Nora Newcomer', $crawler->filter('[data-test-tmonier-company-orders]')->text());
        self::assertNotSame($personalOrder->getNumber(), $companyOrder->getNumber());
        self::assertNotSame($guestOrder->getNumber(), $companyOrder->getNumber());
        self::assertTrue($member->isEnabled());
    }

    public function testTheCompanyOrderPageIsReadOnlyAndHidesContactDetails(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->approverA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/orders/' . $s->orderA->getNumber()));

        self::assertResponseIsSuccessful();
        self::assertSame('Bob Buyer', trim($crawler->filter('[data-test-tmonier-company-order-placed-by]')->text()));
        self::assertStringContainsString('Office chair', $crawler->filter('[data-test-tmonier-company-order-items]')->text());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('buyer-a-' . $this->catalog()->suffix, $html);
        self::assertStringNotContainsString('555 0100', $html, 'No address phone number.');
        self::assertStringNotContainsString('1 Main Street', $html, 'No address.');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-order-details] a, [data-test-tmonier-company-order-items] a, [data-test-tmonier-company-order-items] form'), 'Read-only: no link or action.');
    }

    public function testBuyersDoNotSeeTheCompanyOrdersTab(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company'));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-orders-link]'));
        self::assertSame('Buyer', trim($crawler->filter('[data-test-tmonier-company-role]')->text()));

        $this->logout();
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-company-orders-link]'));
        self::assertSame('4', trim($crawler->filter('[data-test-tmonier-company-members-count]')->text()));
    }

    public function testTheOrdersListIsPaginated(): void
    {
        $company = $this->catalog()->company('Busy');
        $admin = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $orders = $this->catalog()->completedOrders(CompanyScenario::customerOf($admin), 23);

        $this->loginShopUser(CompanyScenario::customerOf($admin));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/orders'));
        $firstPage = $crawler->filter('[data-test-tmonier-company-order]')->each(static fn ($row): string => (string) $row->attr('data-test-tmonier-company-order'));
        self::assertCount(2, $crawler->filter('.pagination .page-item'));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/orders?page=2'));
        $secondPage = $crawler->filter('[data-test-tmonier-company-order]')->each(static fn ($row): string => (string) $row->attr('data-test-tmonier-company-order'));

        self::assertCount(20, $firstPage);
        self::assertCount(3, $secondPage);
        $all = array_map(static fn ($order): string => (string) $order->getNumber(), $orders);
        self::assertEqualsCanonicalizing($all, array_merge($firstPage, $secondPage));
    }
}
