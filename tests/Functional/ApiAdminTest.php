<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;

final class ApiAdminTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testAdministratorsManageCompaniesAndMembers(): void
    {
        $headers = $this->adminApiHeaders();
        $group = $this->catalog()->customerGroup();
        $code = 'API_' . strtoupper($this->catalog()->suffix);

        $this->client->request('POST', '/api/v2/admin/companies', server: $headers, content: (string) json_encode([
            'code' => $code,
            'name' => 'Api Corp',
            'vatNumber' => 'DE123456789',
            'registrationNumber' => 'HRB 12345',
            'defaultCustomerGroup' => '/api/v2/admin/customer-groups/' . $group->getCode(),
            'enabled' => true,
        ]));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('Api Corp', $this->json()['name']);

        $this->client->request('POST', '/api/v2/admin/companies', server: $headers, content: (string) json_encode(['code' => $code, 'name' => 'Duplicate']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('This code is already used by another company.', (string) $this->client->getResponse()->getContent());

        $customer = $this->catalog()->customer('api-member');
        $this->client->request('POST', '/api/v2/admin/company-members', server: $headers, content: (string) json_encode([
            'company' => '/api/v2/admin/companies/' . $code,
            'customer' => '/api/v2/admin/customers/' . $customer->getId(),
            'role' => 'admin',
            'enabled' => true,
        ]));
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $memberId = $this->json()['id'];
        self::assertIsInt($memberId);
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find($customer::class, $customer->getId());
        self::assertSame($group->getCode(), $reloaded?->getGroup()?->getCode(), 'The default customer group is applied.');

        // One company per customer.
        $this->client->request('POST', '/api/v2/admin/company-members', server: $headers, content: (string) json_encode([
            'company' => '/api/v2/admin/companies/' . $code,
            'customer' => '/api/v2/admin/customers/' . $customer->getId(),
            'role' => 'buyer',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('This customer already belongs to a company.', (string) $this->client->getResponse()->getContent());

        $this->client->request('PUT', '/api/v2/admin/company-members/' . $memberId, server: $headers, content: (string) json_encode(['role' => 'approver', 'enabled' => false]));
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame(['approver', false], [$this->json()['role'], $this->json()['enabled']]);

        $this->client->request('PUT', '/api/v2/admin/company-members/' . $memberId, server: $headers, content: (string) json_encode(['role' => 'owner']));
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/v2/admin/companies/' . $code, server: $headers);
        self::assertResponseIsSuccessful();
        $company = $this->json();
        self::assertIsArray($company['members']);
        self::assertCount(1, $company['members']);

        $this->client->request('GET', '/api/v2/admin/company-members?company.code=' . $code, server: $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['hydra:totalItems'] ?? $this->json()['totalItems'] ?? null);

        $this->client->request('DELETE', '/api/v2/admin/company-members/' . $memberId, server: $headers);
        self::assertResponseStatusCodeSame(204);
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find($customer::class, $customer->getId());
        self::assertNull($reloaded?->getGroup(), 'The company group is taken back on detach.');

        $this->client->request('DELETE', '/api/v2/admin/companies/' . $code, server: $headers);
        self::assertResponseStatusCodeSame(204);
        self::assertNotInstanceOf(CompanyInterface::class, $this->companyRepository()->findOneByCode($code));
    }

    public function testShopCustomersCannotReachTheAdminApi(): void
    {
        $company = $this->catalog()->company('Locked');
        $admin = $this->catalog()->member($company, null, 'admin');

        $this->client->request('GET', '/api/v2/admin/companies', server: $this->shopApiHeaders(CompanyScenario::customerOf($admin)));
        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
        $this->client->request('GET', '/api/v2/admin/companies');
        self::assertResponseStatusCodeSame(401);
    }

    private function companyRepository(): CompanyRepositoryInterface
    {
        $repository = self::getContainer()->get('tmonier_company_accounts.repository.company');
        self::assertInstanceOf(CompanyRepositoryInterface::class, $repository);

        return $repository;
    }
}
