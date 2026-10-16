<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * The "My company" tab bar is the `tmonier_company_accounts.shop.account.company.tabs` hook; the
 * test application adds an extra tab through it, as another plugin would.
 */
final class ShopCompanyTabsTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheTabsAreHookablesWithTheActiveTab(): void
    {
        $company = $this->catalog()->company('Tabbed');
        $admin = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $this->loginShopUser(CompanyScenario::customerOf($admin));

        $pages = [
            '/account/company' => 'overview',
            '/account/company/members' => 'members',
            '/account/company/orders' => 'orders',
        ];
        foreach ($pages as $path => $active) {
            $crawler = $this->client->request('GET', $this->shopUrl($path));
            self::assertResponseIsSuccessful();
            $tabs = $crawler->filter('[data-test-tmonier-company-navigation] > li');
            self::assertSame(
                [$this->shopUrl('/account/company'), $this->shopUrl('/account/company/members'), $this->shopUrl('/account/company/orders'), $this->shopUrl('/account/company/addresses'), '#extra'],
                $tabs->filter('a')->each(static fn ($link): string => (string) $link->attr('href')),
                $path,
            );
            self::assertSame($this->shopUrl($path), $crawler->filter('[data-test-tmonier-company-navigation] a.active[aria-current="page"]')->attr('href'), $path);
            // The tab of another plugin gets the company, the member and the active tab key.
            $extra = $crawler->filter('[data-test-test-extra-tab]');
            self::assertSame($active, $extra->attr('data-test-test-extra-tab'), $path);
            self::assertStringContainsString(sprintf('Extra %s (admin)', (string) $company->getName()), $extra->text());
        }
    }

    public function testBuyersDoNotGetTheCompanyOrdersTab(): void
    {
        $buyer = $this->catalog()->member($this->catalog()->company('Buyers'));
        $this->loginShopUser(CompanyScenario::customerOf($buyer));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company'));

        self::assertSame(4, $crawler->filter('[data-test-tmonier-company-navigation] > li')->count(), 'Overview, members, addresses and the extra tab: no orders tab.');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-orders-link]'));
    }
}
