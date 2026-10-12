<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepositoryInterface;

final class AdminCompanyTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheGridListsAndFiltersTheCompanies(): void
    {
        $company = $this->catalog()->company('Listed');
        $this->catalog()->member($company);
        $this->catalog()->member($company);
        $other = $this->catalog()->company('Hidden');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/companies/?criteria[search][value]=' . $company->getCode());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Companies', $crawler->filter('title')->text());
        self::assertSame('2', trim($crawler->filter(sprintf('[data-test-tmonier-company-members-count="%s"]', (string) $company->getCode()))->text()));
        self::assertCount(0, $crawler->filter(sprintf('[data-test-tmonier-company-members-count="%s"]', (string) $other->getCode())));
        self::assertCount(1, $crawler->filter('a[href$="/admin/companies/' . $company->getId() . '/members"]')->reduce(static fn ($link): bool => '2' !== trim($link->text())));
    }

    public function testAnAdministratorCreatesAndUpdatesACompany(): void
    {
        $group = $this->catalog()->customerGroup('Partners');
        $code = 'NEW_' . strtoupper($this->catalog()->suffix);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/companies/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="tmonier_company_accounts_company"]')->form([
            'tmonier_company_accounts_company[code]' => $code,
            'tmonier_company_accounts_company[name]' => 'New Company',
            'tmonier_company_accounts_company[vatNumber]' => ' FR40123456824 ',
            'tmonier_company_accounts_company[registrationNumber]' => '',
            'tmonier_company_accounts_company[defaultCustomerGroup]' => $group->getCode(),
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $company = $this->companyRepository()->findOneByCode($code);
        self::assertInstanceOf(CompanyInterface::class, $company);
        self::assertSame('FR40123456824', $company->getVatNumber());
        self::assertNull($company->getRegistrationNumber());
        self::assertSame($group->getId(), $company->getDefaultCustomerGroup()?->getId());

        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-company-members-button]'));
        $form = $crawler->filter('form[name="tmonier_company_accounts_company"]')->form([
            'tmonier_company_accounts_company[name]' => '',
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Please enter the company name.', (string) $this->client->getResponse()->getContent());
    }

    public function testMembersAreAttachedEditedAndDetached(): void
    {
        $company = $this->catalog()->company('Staffed');
        $customer = $this->catalog()->customer('attached', true, 'Tess', 'Attached');
        $existing = $this->catalog()->member($this->catalog()->company('Elsewhere'));
        $this->loginAdmin();
        $base = sprintf('/admin/companies/%d/members', $this->idOf($company));

        $crawler = $this->client->request('GET', $base);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $company->getName(), $crawler->filter('[data-test-tmonier-company-members-title]')->text());

        $crawler = $this->client->request('GET', $base . '/new');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $company->getName(), $crawler->filter('[data-test-tmonier-company-member-title]')->text());
        self::assertStringContainsString((string) $company->getName(), $crawler->filter('.breadcrumb')->text());
        // Required marker on the role field only, not on each role; the radios stay required.
        $radios = $crawler->filter('input[name="tmonier_company_accounts_company_member[role]"]');
        self::assertCount(3, $radios);
        self::assertCount(3, $radios->filter('[required]'));
        self::assertCount(0, $crawler->filter('label.required[for^="tmonier_company_accounts_company_member_role_"]'));
        self::assertCount(1, $crawler->filter('.required')->reduce(static fn ($node): bool => str_contains($node->text(), 'Role')));
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $this->choice($form, 'tmonier_company_accounts_company_member[customer]')->disableValidation()->setValue((string) $this->idOf($customer));
        $this->choice($form, 'tmonier_company_accounts_company_member[role]')->setValue('admin');
        $this->client->submit($form);
        self::assertResponseRedirects($base);

        $member = $this->memberRepository()->findOneByCustomer($customer);
        self::assertInstanceOf(CompanyMemberInterface::class, $member);
        self::assertSame($company->getId(), $member->getCompany()?->getId());
        self::assertSame('admin', $member->getRole());

        // A customer of another company is not offered and cannot be attached.
        $crawler = $this->client->request('GET', $base . '/new');
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $this->choice($form, 'tmonier_company_accounts_company_member[customer]')->disableValidation()->setValue((string) $this->idOf(CompanyScenario::customerOf($existing)));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertNotSame($company->getId(), $this->memberRepository()->findOneByCustomer(CompanyScenario::customerOf($existing))?->getCompany()?->getId());

        $crawler = $this->client->request('GET', $base);
        self::assertStringContainsString('Tess', $crawler->filter('table')->text());

        $crawler = $this->client->request('GET', sprintf('%s/%d/edit', $base, $this->idOf($member)));
        self::assertResponseIsSuccessful();
        // Whom and which company the form is about.
        self::assertStringContainsString('Tess Attached', $crawler->filter('[data-test-tmonier-company-member-title]')->text());
        self::assertStringContainsString((string) $company->getName(), $crawler->filter('[data-test-tmonier-company-member-title]')->text());
        self::assertStringContainsString((string) $customer->getEmail(), $crawler->filter('[data-test-tmonier-company-member-context]')->text());
        self::assertStringContainsString('Tess Attached', $crawler->filter('.breadcrumb')->text());
        self::assertCount(0, $crawler->filter('[name="tmonier_company_accounts_company_member[customer]"]'), 'The customer is fixed.');
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $this->choice($form, 'tmonier_company_accounts_company_member[role]')->setValue('buyer');
        $this->choice($form, 'tmonier_company_accounts_company_member[enabled]')->untick();
        $this->client->submit($form);
        self::assertResponseRedirects($base);
        $member = $this->reload($member);
        self::assertSame(['buyer', false], [$member->getRole(), $member->isEnabled()]);

        // Members of a company cannot be edited through another company URL.
        $this->client->request('GET', sprintf('/admin/companies/%d/members/%d/edit', $this->idOf($company), $this->idOf($existing)));
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', $base);
        $deleteForm = $crawler->filter(sprintf('form[action$="%s/%d"]', $base, $this->idOf($member)))->form();
        $this->client->submit($deleteForm);
        self::assertResponseRedirects($base);
        $this->entityManager()->clear();
        self::assertNull($this->memberRepository()->findOneByCustomer($customer));
    }

    public function testMembersOfAnotherCompanyCannotBeChangedThroughACompanyUrl(): void
    {
        $company = $this->catalog()->company('Url');
        $this->catalog()->member($company);
        $other = $this->catalog()->member($this->catalog()->company('Foreign'), null, CompanyMemberInterface::ROLE_ADMIN);
        $this->loginAdmin();
        $base = sprintf('/admin/companies/%d/members', $this->idOf($company));

        // With a valid token for that member: the 404 comes from the company scope.
        $token = $this->sessionCsrfToken((string) $this->idOf($other));
        $this->client->request('DELETE', sprintf('%s/%d', $base, $this->idOf($other)), ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('PUT', sprintf('%s/%d/edit', $base, $this->idOf($other)), [
            'tmonier_company_accounts_company_member' => ['role' => 'buyer'],
        ]);
        self::assertResponseStatusCodeSame(404);

        $other = $this->reload($other);
        self::assertSame('admin', $other->getRole());
        self::assertNotSame($company->getId(), $other->getCompany()?->getId());
    }

    public function testTheCustomerAutocompleteOffersOnlyCustomersWhoCanJoin(): void
    {
        $free = $this->catalog()->customer('free', true, 'Autocomplete', 'Free');
        $taken = $this->catalog()->customer('taken', true, 'Autocomplete', 'Taken');
        $this->catalog()->member($this->catalog()->company('Taken'), $taken);
        $guest = $this->catalog()->customer('guest', false, 'Autocomplete', 'Guest');
        $this->loginAdmin();

        $this->client->request('GET', '/admin/ajax/autocomplete/tmonier_company_accounts_customer?query=' . $this->catalog()->suffix);
        self::assertResponseIsSuccessful();
        $results = $this->json()['results'] ?? null;
        self::assertIsArray($results);
        $emails = (string) json_encode(array_column($results, 'text'));

        self::assertStringContainsString((string) $free->getEmail(), $emails);
        self::assertStringNotContainsString((string) $taken->getEmail(), $emails);
        self::assertStringNotContainsString((string) $guest->getEmail(), $emails);
    }

    public function testTheCustomerPageShowsTheCompany(): void
    {
        $company = $this->catalog()->company('Shown');
        $member = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_APPROVER);
        $loner = $this->catalog()->customer('loner');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', sprintf('/admin/customers/%d', $this->idOf(CompanyScenario::customerOf($member))));
        self::assertResponseIsSuccessful();
        $cell = $crawler->filter('[data-test-tmonier-customer-company]');
        self::assertStringContainsString((string) $company->getName(), $cell->text());
        self::assertStringContainsString('Approver', $cell->text());

        $crawler = $this->client->request('GET', sprintf('/admin/customers/%d', $this->idOf($loner)));
        self::assertSame('-', trim($crawler->filter('[data-test-tmonier-customer-company]')->text()));
    }

    public function testTheMenuLinksToTheCompanies(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/');

        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/companies/"]')->count());
    }

    private function companyRepository(): CompanyRepositoryInterface
    {
        $repository = self::getContainer()->get('tmonier_company_accounts.repository.company');
        self::assertInstanceOf(CompanyRepositoryInterface::class, $repository);

        return $repository;
    }

    private function memberRepository(): CompanyMemberRepositoryInterface
    {
        $repository = self::getContainer()->get('tmonier_company_accounts.repository.company_member');
        self::assertInstanceOf(CompanyMemberRepositoryInterface::class, $repository);

        return $repository;
    }
}
