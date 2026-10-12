<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/** Shop API resources, with the same anti-IDOR matrix as the account pages. */
final class ApiShopTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testAMemberReadsTheirCompany(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->client->request('GET', '/api/v2/shop/company', server: $this->shopApiHeaders(CompanyScenario::customerOf($s->adminA)));

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame($s->companyA->getCode(), $data['code']);
        self::assertSame($s->companyA->getName(), $data['name']);
        self::assertSame('FR40123456824', $data['vatNumber']);
        self::assertSame('admin', $data['role']);
        self::assertSame(4, $data['membersCount']);
        self::assertTrue($data['canManageMembers']);
        self::assertTrue($data['canViewCompanyOrders']);

        $this->client->request('GET', '/api/v2/shop/company', server: $this->shopApiHeaders(CompanyScenario::customerOf($s->buyerA)));
        $data = $this->json();
        self::assertFalse($data['canManageMembers']);
        self::assertFalse($data['canViewCompanyOrders']);
    }

    public function testAccessMatrix(): void
    {
        $s = new CompanyScenario($this->catalog());
        $orderA = '/api/v2/shop/company/orders/' . $s->orderA->getNumber();
        $memberOfB = '/api/v2/shop/company/members/' . $this->idOf($s->adminB);

        $cases = [
            // path => [actor, expected]
            ['/api/v2/shop/company', $s->adminA, 200],
            ['/api/v2/shop/company', $s->disabledA, 403],
            ['/api/v2/shop/company', $s->adminC, 403],
            ['/api/v2/shop/company', $s->nonMember, 404],
            ['/api/v2/shop/company', null, 401],
            ['/api/v2/shop/company/members', $s->buyerA, 200],
            ['/api/v2/shop/company/members', $s->disabledA, 403],
            ['/api/v2/shop/company/members', $s->adminC, 403],
            ['/api/v2/shop/company/members', $s->nonMember, 404],
            ['/api/v2/shop/company/members', null, 401],
            ['/api/v2/shop/company/members/' . $this->idOf($s->buyerA), $s->adminA, 200],
            [$memberOfB, $s->adminA, 404],
            ['/api/v2/shop/company/orders', $s->adminA, 200],
            ['/api/v2/shop/company/orders', $s->approverA, 200],
            ['/api/v2/shop/company/orders', $s->buyerA, 403],
            ['/api/v2/shop/company/orders', $s->disabledA, 403],
            ['/api/v2/shop/company/orders', $s->adminC, 403],
            ['/api/v2/shop/company/orders', $s->nonMember, 404],
            ['/api/v2/shop/company/orders', null, 401],
            [$orderA, $s->adminA, 200],
            [$orderA, $s->buyerA, 403],
            [$orderA, $s->adminB, 404],
            [$orderA, $s->adminC, 403],
            [$orderA, $s->nonMember, 404],
            [$orderA, null, 401],
            ['/api/v2/shop/company/orders/' . $s->orderB->getNumber(), $s->adminA, 404],
        ];
        foreach ($cases as [$path, $actor, $expected]) {
            $this->client->request('GET', $path, server: $this->shopApiHeaders(null === $actor ? null : CompanyScenario::customerOf($actor)));
            self::assertSame($expected, $this->client->getResponse()->getStatusCode(), sprintf('%s as %s: %s', $path, null === $actor ? 'anonymous' : (string) CompanyScenario::customerOf($actor)->getEmail(), (string) $this->client->getResponse()->getContent()));
        }
    }

    public function testMembersAreListedWithoutContactDetails(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->client->request('GET', '/api/v2/shop/company/members', server: $this->shopApiHeaders(CompanyScenario::customerOf($s->buyerA)));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('@company-accounts.test', $content);
        self::assertStringNotContainsString('12 34 56 78', $content);
        $members = $this->json()['hydra:member'] ?? $this->json()['member'] ?? null;
        self::assertIsArray($members);
        self::assertCount(4, $members);
        $bob = array_values(array_filter($members, static fn ($member): bool => is_array($member) && 'Bob' === $member['firstName']));
        self::assertCount(1, $bob);
        self::assertIsArray($bob[0]);
        self::assertSame(['Buyer', 'buyer', true, true], [$bob[0]['lastName'], $bob[0]['role'], $bob[0]['enabled'], $bob[0]['me']]);
        foreach ($members as $member) {
            self::assertIsArray($member);
            self::assertSame([], array_values(array_intersect(array_keys($member), ['email', 'phoneNumber', 'customer', 'user'])));
        }
    }

    public function testACompanyAdminUpdatesAMember(): void
    {
        $s = new CompanyScenario($this->catalog());
        $path = '/api/v2/shop/company/members/' . $this->idOf($s->buyerA);
        $patch = fn (?CompanyMemberInterface $as, array $body, string $target = '') => $this->client->request('PATCH', '' === $target ? $path : $target, server: $this->shopApiHeaders(null === $as ? null : CompanyScenario::customerOf($as), 'application/merge-patch+json'), content: json_encode($body, \JSON_THROW_ON_ERROR));

        $patch($s->adminA, ['role' => 'approver', 'enabled' => false]);
        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame(['approver', false, false], [$data['role'], $data['enabled'], $data['me']]);
        $buyer = $this->reload($s->buyerA);
        self::assertSame('approver', $buyer->getRole());
        self::assertFalse($buyer->isEnabled());

        $patch($s->adminA, ['role' => 'owner']);
        self::assertResponseStatusCodeSame(422);
        $patch($s->adminA, ['role' => 'buyer'], '/api/v2/shop/company/members/' . $this->idOf($s->adminA));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('You cannot change your own role or status.', (string) $this->client->getResponse()->getContent());

        $patch($s->approverA, ['role' => 'admin'], '/api/v2/shop/company/members/' . $this->idOf($s->approverA));
        self::assertResponseStatusCodeSame(403);
        $patch($s->approverA, ['enabled' => true]);
        self::assertResponseStatusCodeSame(403);
        $patch($s->disabledA, ['enabled' => true]);
        self::assertResponseStatusCodeSame(403);
        $patch($s->adminB, ['enabled' => true]);
        self::assertResponseStatusCodeSame(404);
        $patch($s->adminC, ['enabled' => true]);
        self::assertResponseStatusCodeSame(403);
        $patch(null, ['enabled' => true]);
        self::assertResponseStatusCodeSame(401);
        $this->client->request('PATCH', $path, server: $this->shopApiHeaders(CompanyScenario::customerOf($s->nonMember), 'application/merge-patch+json'), content: '{"enabled": true}');
        self::assertResponseStatusCodeSame(404);

        $buyer = $this->reload($s->buyerA);
        self::assertFalse($buyer->isEnabled(), 'Unchanged by the refused requests.');
    }

    public function testCompanyOrdersAreReadOnlyViews(): void
    {
        $s = new CompanyScenario($this->catalog());

        $this->client->request('GET', '/api/v2/shop/company/orders', server: $this->shopApiHeaders(CompanyScenario::customerOf($s->approverA)));
        self::assertResponseIsSuccessful();
        $data = $this->json();
        $orders = $data['hydra:member'] ?? $data['member'] ?? null;
        self::assertIsArray($orders);
        self::assertCount(1, $orders);
        self::assertSame(1, $data['hydra:totalItems'] ?? $data['totalItems'] ?? null);

        $this->client->request('GET', '/api/v2/shop/company/orders/' . $s->orderA->getNumber(), server: $this->shopApiHeaders(CompanyScenario::customerOf($s->approverA)));
        self::assertResponseIsSuccessful();
        $order = $this->json();
        self::assertSame($s->orderA->getNumber(), $order['number']);
        self::assertSame('Bob Buyer', $order['placedBy']);
        self::assertIsArray($order['items']);
        self::assertSame(2, $order['items'][0]['quantity'] ?? null);
        self::assertSame($s->orderA->getTotal(), $order['total']);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('@company-accounts.test', $content);
        self::assertStringNotContainsString('Main Street', $content);
        self::assertStringNotContainsString('tokenValue', $content);
    }

    public function testTheOperationsAreDocumented(): void
    {
        $this->client->request('GET', '/api/v2/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        self::assertResponseIsSuccessful();
        $paths = $this->json()['paths'] ?? null;
        self::assertIsArray($paths);
        foreach (['/api/v2/shop/company', '/api/v2/shop/company/members', '/api/v2/shop/company/members/{id}', '/api/v2/shop/company/orders', '/api/v2/shop/company/orders/{number}', '/api/v2/admin/companies', '/api/v2/admin/company-members'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

}
