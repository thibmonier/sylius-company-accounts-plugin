<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Anti-IDOR matrix of the shop account pages: other company, disabled member, disabled company,
 * non-member and anonymous visitor, for every route.
 */
final class ShopCompanyAccessTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testCompanyPages(): void
    {
        $s = new CompanyScenario($this->catalog());

        foreach (['/account/company', '/account/company/members'] as $path) {
            $this->assertStatuses($path, [
                'admin A' => [$s->adminA, 200],
                'buyer A' => [$s->buyerA, 200],
                'approver A' => [$s->approverA, 200],
                'disabled member of A' => [$s->disabledA, 403],
                'admin of disabled company C' => [$s->adminC, 403],
                'non-member' => [$s->nonMember, 404],
                'anonymous' => [null, 302],
            ]);
        }
    }

    public function testCompanyOrdersList(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->assertStatuses('/account/company/orders', [
            'admin A' => [$s->adminA, 200],
            'approver A' => [$s->approverA, 200],
            'buyer A (role not in company_orders_roles)' => [$s->buyerA, 403],
            'disabled member of A' => [$s->disabledA, 403],
            'admin of disabled company C' => [$s->adminC, 403],
            'non-member' => [$s->nonMember, 404],
            'anonymous' => [null, 302],
        ]);
    }

    public function testCompanyOrderPage(): void
    {
        $s = new CompanyScenario($this->catalog());
        $orderA = '/account/company/orders/' . $s->orderA->getNumber();
        $orderB = '/account/company/orders/' . $s->orderB->getNumber();

        $this->assertStatuses($orderA, [
            'admin A' => [$s->adminA, 200],
            'approver A' => [$s->approverA, 200],
            'buyer A, whose order it is' => [$s->buyerA, 403],
            'disabled member of A' => [$s->disabledA, 403],
            'admin B (other company)' => [$s->adminB, 404],
            'admin of disabled company C' => [$s->adminC, 403],
            'non-member' => [$s->nonMember, 404],
            'anonymous' => [null, 302],
        ]);
        $this->assertStatuses($orderB, [
            'admin A (other company)' => [$s->adminA, 404],
            'admin B' => [$s->adminB, 200],
        ]);
        $this->assertStatuses('/account/company/orders/UNKNOWN-' . $this->catalog()->suffix, [
            'admin A' => [$s->adminA, 404],
        ]);
    }

    public function testCoreOrderPagesStayOwnerOnly(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->assertStatuses('/account/orders/' . $s->orderA->getNumber(), [
            'buyer A, owner' => [$s->buyerA, 200],
            'admin A, same company' => [$s->adminA, 404],
        ]);
    }

    public function testMemberUpdateRoute(): void
    {
        $s = new CompanyScenario($this->catalog());
        $target = $this->idOf($s->buyerA);
        $foreign = $this->idOf($s->adminB);

        foreach ([
            'buyer A' => [$s->buyerA, $target, 403],
            'approver A' => [$s->approverA, $target, 403],
            'disabled admin of A' => [$s->disabledA, $target, 403],
            'admin of disabled company C' => [$s->adminC, $target, 403],
            'non-member' => [$s->nonMember, $target, 404],
            'admin A on a member of B' => [$s->adminA, $foreign, 404],
            'admin A on an unknown member' => [$s->adminA, 999999999, 404],
            'anonymous' => [null, $target, 302],
        ] as $label => [$member, $id, $expected]) {
            $this->logout();
            if (null !== $member) {
                $this->loginShopUser(CompanyScenario::customerOf($member));
            }
            $this->client->request('POST', $this->shopUrl('/account/company/members/' . $id), ['role' => 'admin', 'enabled' => '1', '_csrf_token' => 'whatever']);
            self::assertSame($expected, $this->client->getResponse()->getStatusCode(), $label);
        }

        $this->entityManager()->clear();
        self::assertSame('buyer', $this->reload($s->buyerA)->getRole());
        self::assertSame('admin', $this->reload($s->adminB)->getRole());
    }

    public function testTheMenuAndTheDashboardShowTheCompanyToActiveMembersOnly(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-dashboard-company]'));
        self::assertStringContainsString((string) $s->companyA->getName(), $crawler->filter('[data-test-tmonier-dashboard-company]')->text());
        // Account menu link and dashboard card button.
        self::assertCount(2, $crawler->filter(sprintf('a[href="%s"]', $this->shopUrl('/account/company'))));

        foreach ([$s->disabledA, $s->adminC, $s->nonMember] as $member) {
            $this->logout();
            $this->loginShopUser(CompanyScenario::customerOf($member));
            $crawler = $this->client->request('GET', $this->shopUrl('/account/dashboard'));
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('[data-test-tmonier-dashboard-company]'));
            self::assertCount(0, $crawler->filter(sprintf('a[href="%s"]', $this->shopUrl('/account/company'))));
        }
    }

    /** @param array<string, array{0: \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface|CustomerInterface|null, 1: int}> $cases */
    private function assertStatuses(string $path, array $cases): void
    {
        foreach ($cases as $label => [$actor, $expected]) {
            $this->logout();
            if (null !== $actor) {
                $this->loginShopUser(CompanyScenario::customerOf($actor));
            }
            $this->client->request('GET', $this->shopUrl($path));
            $status = $this->client->getResponse()->getStatusCode();
            self::assertSame($expected, $status, sprintf('%s on %s', $label, $path));
            if (302 === $expected) {
                self::assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
            }
        }
    }
}
