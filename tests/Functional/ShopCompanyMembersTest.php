<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

final class ShopCompanyMembersTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testMembersSeeTheNamesAndRolesOfTheirColleaguesButNoContactDetails(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/members'));

        self::assertResponseIsSuccessful();
        $table = $crawler->filter('[data-test-tmonier-company-members]');
        self::assertCount(4, $table->filter('tbody tr'));
        self::assertStringContainsString('Alice Admin', $table->text());
        self::assertStringContainsString('Dan Disabled', $table->text());
        self::assertStringNotContainsString('Bea Other', $table->text());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('admin-a-' . $this->catalog()->suffix, $html, 'No e-mail of a colleague.');
        self::assertStringNotContainsString('12 34 56 78', $html, 'No phone number of a colleague.');
        self::assertCount(0, $crawler->filter('form[data-test-tmonier-company-member-form]'), 'Buyers cannot edit.');
    }

    public function testACompanyAdminChangesTheRoleAndTheStatusOfAColleague(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/members'));
        self::assertCount(3, $crawler->filter('form[data-test-tmonier-company-member-form]'), 'Every colleague but themselves.');
        self::assertCount(0, $crawler->filter(sprintf('[data-test-tmonier-company-member-form="%d"]', $this->idOf($s->adminA))));

        $form = $crawler->filter(sprintf('[data-test-tmonier-company-member-form="%d"]', $this->idOf($s->buyerA)))->form();
        $form['role']->select('approver');
        $form['enabled']->untick();
        $this->client->submit($form);
        self::assertResponseRedirects($this->shopUrl('/account/company/members'));
        $crawler = $this->client->followRedirect();
        self::assertSame(['The member has been updated.'], $this->flashes($crawler));

        $buyer = $this->reload($s->buyerA);
        self::assertSame(CompanyMemberInterface::ROLE_APPROVER, $buyer->getRole());
        self::assertFalse($buyer->isEnabled());

        // The disabled admin can be enabled again.
        $form = $crawler->filter(sprintf('[data-test-tmonier-company-member-form="%d"]', $this->idOf($s->disabledA)))->form();
        $form['enabled']->tick();
        $this->client->submit($form);
        self::assertTrue($this->reload($s->disabledA)->isEnabled());
    }

    public function testAnAdminCannotChangeThemselves(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/members'));
        $token = $crawler->filter(sprintf('[data-test-tmonier-company-member-form="%d"] input[name="_csrf_token"]', $this->idOf($s->buyerA)))->attr('value');
        self::assertNotNull($token);

        // A forged request on their own row, with a valid token of their session.
        $tokenManager = self::getContainer()->get('security.csrf.token_manager');
        self::assertInstanceOf(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class, $tokenManager);
        $this->client->request('POST', $this->shopUrl('/account/company/members/' . $this->idOf($s->adminA)), [
            'role' => 'buyer',
            '_csrf_token' => $this->csrfToken('tmonier_company_member_' . $this->idOf($s->adminA)),
        ]);
        $crawler = $this->client->followRedirect();
        self::assertSame(['You cannot change your own role or status.'], $this->flashes($crawler));
        self::assertTrue($this->reload($s->adminA)->isAdmin());
    }

    public function testTheLastEnabledAdminCannotBeDemoted(): void
    {
        $company = $this->catalog()->company('Solo');
        $admin = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $other = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $this->loginShopUser(CompanyScenario::customerOf($admin));

        // Two admins: demoting the other one is fine.
        $this->client->request('POST', $this->shopUrl('/account/company/members/' . $this->idOf($other)), [
            'role' => 'buyer',
            'enabled' => '1',
            '_csrf_token' => $this->csrfToken('tmonier_company_member_' . $this->idOf($other)),
        ]);
        self::assertSame('buyer', $this->reload($other)->getRole());

        // The guard also protects the last admin when the actor is not the target (e.g. a
        // concurrent change): checked directly.
        $guard = self::getContainer()->get('tmonier_company_accounts.member_change_guard');
        self::assertInstanceOf(\Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard::class, $guard);
        $admin = $this->reload($admin);
        self::assertSame('tmonier_company_accounts.member.self_change', $guard->check($admin, $admin, 'buyer', true));
    }

    public function testAnInvalidCsrfTokenOrRoleIsRefused(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));
        $url = $this->shopUrl('/account/company/members/' . $this->idOf($s->buyerA));

        $this->client->request('POST', $url, ['role' => 'admin', 'enabled' => '1', '_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', $url, ['role' => 'owner', 'enabled' => '1', '_csrf_token' => $this->csrfToken('tmonier_company_member_' . $this->idOf($s->buyerA))]);
        $crawler = $this->client->followRedirect();
        self::assertSame(['This role does not exist.'], $this->flashes($crawler));
        self::assertSame('buyer', $this->reload($s->buyerA)->getRole());
    }

    private function csrfToken(string $id): string
    {
        // The token of the browser session: read it from a rendered form.
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/members'));
        $input = $crawler->filter('input[name="_csrf_token"]');
        if (0 === $input->count()) {
            self::fail('No CSRF token on the members page.');
        }
        foreach ($crawler->filter('form[data-test-tmonier-company-member-form]') as $form) {
            if (str_ends_with((string) $form->getAttribute('action'), '/' . substr($id, strlen('tmonier_company_member_')))) {
                return (string) (new \Symfony\Component\DomCrawler\Crawler($form))->filter('input[name="_csrf_token"]')->attr('value');
            }
        }

        // No form for that member (e.g. themselves): generate one in the same session.
        $session = $this->client->getRequest()->getSession();
        $storage = new \Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage(new \Symfony\Component\HttpFoundation\RequestStack([$this->client->getRequest()]));
        $manager = new \Symfony\Component\Security\Csrf\CsrfTokenManager(null, $storage);
        $value = $manager->getToken($id)->getValue();
        $session->save();
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));

        return $value;
    }
}
