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
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $form['tmonier_company_accounts_company_member[customer]']->disableValidation()->setValue((string) $customer->getId());
        $form['tmonier_company_accounts_company_member[role]']->setValue('admin');
        $this->client->submit($form);
        self::assertResponseRedirects($base);

        $member = $this->memberRepository()->findOneByCustomer($customer);
        self::assertInstanceOf(CompanyMemberInterface::class, $member);
        self::assertSame($company->getId(), $member->getCompany()?->getId());
        self::assertSame('admin', $member->getRole());

        // A customer of another company cannot be attached.
        $crawler = $this->client->request('GET', $base . '/new');
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $form['tmonier_company_accounts_company_member[customer]']->disableValidation()->setValue((string) CompanyScenario::customerOf($existing)->getId());
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('This customer already belongs to a company.', (string) $this->client->getResponse()->getContent());

        $crawler = $this->client->request('GET', $base);
        self::assertStringContainsString('Tess', $crawler->filter('table')->text());

        $crawler = $this->client->request('GET', sprintf('%s/%d/edit', $base, $this->idOf($member)));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[name="tmonier_company_accounts_company_member[customer]"]'), 'The customer is fixed.');
        $form = $crawler->filter('form[name="tmonier_company_accounts_company_member"]')->form();
        $form['tmonier_company_accounts_company_member[role]']->setValue('buyer');
        $form['tmonier_company_accounts_company_member[enabled]']->untick();
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

    public function testTheCustomerPageShowsTheCompany(): void
    {
        $company = $this->catalog()->company('Shown');
        $member = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_APPROVER);
        $loner = $this->catalog()->customer('loner');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', sprintf('/admin/customers/%d', (int) CompanyScenario::customerOf($member)->getId()));
        self::assertResponseIsSuccessful();
        $cell = $crawler->filter('[data-test-tmonier-customer-company]');
        self::assertStringContainsString((string) $company->getName(), $cell->text());
        self::assertStringContainsString('Approver', $cell->text());

        $crawler = $this->client->request('GET', sprintf('/admin/customers/%d', (int) $loner->getId()));
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
