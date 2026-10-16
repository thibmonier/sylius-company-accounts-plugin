<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;

/** US-025: the address book shared by the members of a company. */
final class ShopCompanyAddressesTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testAnAdminAddsAnAddressAndEveryMemberSeesItWithoutModificationAction(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses'));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-company-address-add]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-company-addresses-empty]'));

        $crawler = $this->client->click($crawler->filter('[data-test-tmonier-company-address-add]')->link());
        $this->client->submit($crawler->filter('form[name="tmonier_company_accounts_company_address"]')->form($this->validValues('Lyon plant')));
        self::assertResponseRedirects($this->shopUrl('/account/company/addresses'));
        $crawler = $this->client->followRedirect();
        self::assertSame(['The address has been added.'], $this->flashes($crawler));
        self::assertStringContainsString('Lyon plant', $crawler->filter('[data-test-tmonier-company-addresses]')->text());

        $this->logout();
        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Lyon plant', $crawler->filter('[data-test-tmonier-company-addresses]')->text());
        self::assertStringContainsString('12 rue de la Gare', $crawler->filter('[data-test-tmonier-company-addresses]')->text());
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-address-add]'), 'Consultation only.');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-addresses] form'));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-company-addresses] a.btn'));
    }

    public function testANonAdminCannotWriteTheAddressBook(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));
        $token = $this->sessionCsrfToken('tmonier_company_address_' . $this->idOf($entry));

        $this->client->request('GET', $this->shopUrl('/account/company/addresses/new'));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $this->shopUrl(sprintf('/account/company/addresses/%d/edit', $this->idOf($entry))));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/edit', $this->idOf($entry))), ['tmonier_company_accounts_company_address' => ['label' => 'Hacked']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/delete', $this->idOf($entry))), ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(403);

        self::assertSame('Lyon plant', $this->reloadEntry($entry)->getLabel());
    }

    public function testAnAdminEditsAndDeletesAnAddressOfTheirCompanyOnly(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $foreign = $this->entry($s->companyB, 'Beta site');
        $addressId = $entry->getAddress()?->getId();
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));

        $crawler = $this->client->request('GET', $this->shopUrl(sprintf('/account/company/addresses/%d/edit', $this->idOf($entry))));
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="tmonier_company_accounts_company_address"]')->form(['tmonier_company_accounts_company_address[label]' => 'Lyon factory']));
        self::assertResponseRedirects($this->shopUrl('/account/company/addresses'));
        self::assertSame('Lyon factory', $this->reloadEntry($entry)->getLabel());

        // Another company's address: not found, for reading, editing and deleting.
        $this->client->request('GET', $this->shopUrl(sprintf('/account/company/addresses/%d/edit', $this->idOf($foreign))));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/delete', $this->idOf($foreign))), ['_csrf_token' => 'x']);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/delete', $this->idOf($entry))), ['_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses'));
        $this->client->submit($crawler->filter(sprintf('[data-test-tmonier-company-address="%d"] form', $this->idOf($entry)))->form());
        self::assertResponseRedirects($this->shopUrl('/account/company/addresses'));
        $crawler = $this->client->followRedirect();
        self::assertSame(['The address has been deleted.'], $this->flashes($crawler));
        self::assertNull($this->entityManager()->getRepository(CompanyAddressInterface::class)->find($this->idOf($entry)));
        self::assertNull($this->entityManager()->getRepository(AddressInterface::class)->find($addressId), 'The owned Sylius address goes too.');
        self::assertNotNull($this->entityManager()->getRepository(CompanyAddressInterface::class)->find($this->idOf($foreign)));
    }

    public function testAnInvalidAddressShowsErrorsAndSavesNothing(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->adminA));
        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses/new'));

        $values = $this->validValues('No postcode');
        $values['tmonier_company_accounts_company_address[address][postcode]'] = '';
        $crawler = $this->client->submit($crawler->filter('form[name="tmonier_company_accounts_company_address"]')->form($values));

        self::assertResponseStatusCodeSame(422);
        self::assertGreaterThan(0, $crawler->filter('.invalid-feedback, .form-error-message')->count());
        self::assertSame([], $this->repository()->findByCompany($s->companyA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses/new'));
        $values = $this->validValues('');
        $this->client->submit($crawler->filter('form[name="tmonier_company_accounts_company_address"]')->form($values));
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->repository()->findByCompany($s->companyA));
    }

    public function testMembersPickASharedAddressAtTheCheckout(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $customer = CompanyScenario::customerOf($s->buyerA);
        $cart = $this->catalog()->cart($customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->shopUrl('/checkout/address'));
        self::assertResponseIsSuccessful();
        $box = $crawler->filter('[data-test-tmonier-checkout-company-addresses]');
        self::assertCount(1, $box);
        self::assertStringContainsString('Lyon plant', $box->text());

        $this->client->submit($box->filter(sprintf('[data-test-tmonier-checkout-company-address="%d"] form', $this->idOf($entry)))->form());
        self::assertResponseRedirects($this->shopUrl('/checkout/address'));
        $this->client->followRedirect();

        $order = $this->entityManager()->getRepository(OrderInterface::class)->find($cart->getId());
        self::assertInstanceOf(OrderInterface::class, $order);
        $this->entityManager()->refresh($order);
        $shipping = $order->getShippingAddress();
        self::assertInstanceOf(AddressInterface::class, $shipping);
        self::assertSame('12 rue de la Gare', $shipping->getStreet());
        self::assertNotSame($entry->getAddress()?->getId(), $shipping->getId(), 'The order has its own copy.');
        self::assertStringContainsString('12 rue de la Gare', (string) $this->client->getResponse()->getContent());
    }

    public function testPickingAnAddressTwiceReusesTheShippingAddressOfTheOrder(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $customer = CompanyScenario::customerOf($s->buyerA);
        $this->catalog()->cart($customer);
        $this->loginShopUser($customer);
        $before = $this->addressRows();

        $this->pick($entry);
        $afterFirst = $this->addressRows();
        $this->pick($entry);
        $afterSecond = $this->addressRows();

        self::assertSame($before + 1, $afterFirst);
        self::assertSame($afterFirst, $afterSecond, 'The second pick copies into the existing address.');
    }

    public function testPickingAnAddressSendsTheCheckoutBackAndRecalculatesTheCart(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $customer = CompanyScenario::customerOf($s->buyerA);
        $cart = $this->catalog()->cart($customer);
        $cart->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
        $this->entityManager()->flush();
        $this->loginShopUser($customer);

        $this->pick($entry);

        $order = $this->entityManager()->getRepository(OrderInterface::class)->find($cart->getId());
        self::assertInstanceOf(OrderInterface::class, $order);
        $this->entityManager()->refresh($order);
        self::assertSame(OrderCheckoutStates::STATE_CART, $order->getCheckoutState());
        self::assertSame($order->getItemsTotal() + $order->getAdjustmentsTotal(), $order->getTotal(), 'The cart has been processed.');
        $this->client->request('GET', $this->shopUrl('/checkout/complete'));
        self::assertResponseRedirects();
    }

    public function testALabelMadeOfSpacesIsBlank(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, '   ');

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($entry, null, ['sylius']);

        self::assertGreaterThan(0, count(array_filter(iterator_to_array($violations), static fn (ConstraintViolationInterface $v): bool => 'label' === $v->getPropertyPath())));
    }

    private function addressRows(): int
    {
        $count = $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM sylius_address');

        return is_numeric($count) ? (int) $count : 0;
    }

    private function pick(CompanyAddressInterface $entry): void
    {
        $token = $this->sessionCsrfToken('tmonier_company_address_use_' . $this->idOf($entry));
        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/use', $this->idOf($entry))), ['_csrf_token' => $token]);
        self::assertResponseRedirects($this->shopUrl('/checkout/address'));
    }

    public function testACompanyWithoutSharedAddressKeepsTheStandardCheckout(): void
    {
        $s = new CompanyScenario($this->catalog());
        $customer = CompanyScenario::customerOf($s->buyerA);
        $this->catalog()->cart($customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->shopUrl('/checkout/address'));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-checkout-company-addresses]'));
    }

    public function testNeitherACustomerWithoutCompanyNorAMemberOfADisabledCompanyIsOfferedAddresses(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->entry($s->companyC, 'Closed site');
        foreach ([$s->nonMember, CompanyScenario::customerOf($s->adminC)] as $other) {
            $this->catalog()->cart($other);
            $this->loginShopUser($other);
            $crawler = $this->client->request('GET', $this->shopUrl('/checkout/address'));
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('[data-test-tmonier-checkout-company-addresses]'));
        }
    }

    public function testAnAddressOfAnotherCompanyIsRejectedAtTheCheckout(): void
    {
        $s = new CompanyScenario($this->catalog());
        $foreign = $this->entry($s->companyB, 'Beta site');
        $customer = CompanyScenario::customerOf($s->buyerA);
        $cart = $this->catalog()->cart($customer);
        $this->loginShopUser($customer);
        $token = $this->sessionCsrfToken('tmonier_company_address_use_' . $this->idOf($foreign));

        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/use', $this->idOf($foreign))), ['_csrf_token' => $token]);

        self::assertResponseStatusCodeSame(404);
        $order = $this->entityManager()->getRepository(OrderInterface::class)->find($cart->getId());
        self::assertInstanceOf(OrderInterface::class, $order);
        self::assertNull($order->getShippingAddress());
    }

    public function testADisabledMemberCannotUseOrListAddresses(): void
    {
        $s = new CompanyScenario($this->catalog());
        $entry = $this->entry($s->companyA, 'Lyon plant');
        $this->loginShopUser(CompanyScenario::customerOf($s->disabledA));

        $this->client->request('GET', $this->shopUrl('/account/company/addresses'));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $this->shopUrl(sprintf('/account/company/addresses/%d/use', $this->idOf($entry))), ['_csrf_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAddressesTabIsInTheCompanyNavigation(): void
    {
        $s = new CompanyScenario($this->catalog());
        $this->loginShopUser(CompanyScenario::customerOf($s->buyerA));

        $crawler = $this->client->request('GET', $this->shopUrl('/account/company/addresses'));

        self::assertSame($this->shopUrl('/account/company/addresses'), $crawler->filter('[data-test-tmonier-company-navigation] a.active[aria-current="page"]')->attr('href'));
    }

    /** @return array<string, string> */
    private function validValues(string $label): array
    {
        return [
            'tmonier_company_accounts_company_address[label]' => $label,
            'tmonier_company_accounts_company_address[address][firstName]' => 'Isabelle',
            'tmonier_company_accounts_company_address[address][lastName]' => 'Martin',
            'tmonier_company_accounts_company_address[address][countryCode]' => 'US',
            'tmonier_company_accounts_company_address[address][street]' => '12 rue de la Gare',
            'tmonier_company_accounts_company_address[address][city]' => 'Lyon',
            'tmonier_company_accounts_company_address[address][postcode]' => '69003',
        ];
    }

    private function entry(CompanyInterface $company, string $label): CompanyAddressInterface
    {
        /** @var FactoryInterface<CompanyAddressInterface> $factory */
        $factory = self::getContainer()->get('tmonier_company_accounts.factory.company_address');
        /** @var FactoryInterface<AddressInterface> $addressFactory */
        $addressFactory = self::getContainer()->get('sylius.factory.address');
        $address = $addressFactory->createNew();
        $address->setFirstName('Isabelle');
        $address->setLastName('Martin');
        $address->setCountryCode('US');
        $address->setStreet('12 rue de la Gare');
        $address->setCity('Lyon');
        $address->setPostcode('69003');
        $entry = $factory->createNew();
        $entry->setCompany($company);
        $entry->setLabel($label);
        $entry->setAddress($address);
        $this->entityManager()->persist($entry);
        $this->entityManager()->flush();

        return $entry;
    }

    private function reloadEntry(CompanyAddressInterface $entry): CompanyAddressInterface
    {
        $reloaded = $this->reload($entry);
        self::assertInstanceOf(CompanyAddressInterface::class, $reloaded);

        return $reloaded;
    }

    private function repository(): CompanyAddressRepositoryInterface
    {
        $repository = self::getContainer()->get('tmonier_company_accounts.repository.company_address');
        self::assertInstanceOf(CompanyAddressRepositoryInterface::class, $repository);

        return $repository;
    }
}
