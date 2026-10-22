<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusAdminOrderCreationPlugin\Address\OrderAddressSourcesInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Bridge\AdminOrderCreation\CompanyAddressSource;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

/** The addresses of the company offered at the address step of the orders created by staff. */
final class AdminOrderAddressSourceTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheAddressesOfTheCompanyOfTheCustomerAreOffered(): void
    {
        $company = $this->catalog()->company('Acme');
        $first = $this->entry($company, 'Lyon plant');
        $second = $this->entry($company, 'Paris office');
        $customer = $this->catalog()->member($company)->getCustomer();

        $addresses = $this->source()->getAddresses($this->orderOf($customer));

        self::assertSame([$first->getId(), $second->getId()], array_keys($addresses));
        self::assertSame($first->getAddress(), array_values($addresses)[0]);
    }

    public function testTheAddressesOfAnotherCompanyAreNeverOffered(): void
    {
        $company = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('BuildCo');
        $own = $this->entry($company, 'Lyon plant');
        $foreign = $this->entry($other, 'Foreign plant');
        $customer = $this->catalog()->member($company)->getCustomer();

        $addresses = $this->source()->getAddresses($this->orderOf($customer));

        self::assertSame([$own->getId()], array_keys($addresses));
        self::assertArrayNotHasKey($foreign->getId(), $addresses);
    }

    public function testNothingIsOfferedWithoutAnActiveMembership(): void
    {
        $company = $this->catalog()->company('Acme');
        $this->entry($company, 'Lyon plant');
        $outsider = $this->catalog()->customer('outsider');
        $disabledMember = $this->catalog()->member($company, null, 'ROLE_BUYER', false)->getCustomer();
        $disabledCompany = $this->catalog()->company('Dormant', false);
        $this->entry($disabledCompany, 'Dormant plant');
        $ofDisabledCompany = $this->catalog()->member($disabledCompany)->getCustomer();

        self::assertSame([], $this->source()->getAddresses($this->orderOf($outsider)));
        self::assertSame([], $this->source()->getAddresses($this->orderOf($disabledMember)));
        self::assertSame([], $this->source()->getAddresses($this->orderOf($ofDisabledCompany)));
        self::assertSame([], $this->source()->getAddresses(new Order()), 'A draft without customer has no company.');
    }

    public function testTheSourceIsRegisteredBeforeTheAddressBook(): void
    {
        $company = $this->catalog()->company('Acme');
        $entry = $this->entry($company, 'Lyon plant');
        $customer = $this->catalog()->member($company)->getCustomer();
        $order = $this->orderOf($customer);

        $sources = $this->service('tmonier_admin_order.address_sources', OrderAddressSourcesInterface::class);

        self::assertSame(['company'], array_map(static fn (\Tmonier\SyliusAdminOrderCreationPlugin\Address\AddressGroup $group): string => $group->code, $sources->getGroups($order)));
        self::assertSame($entry->getAddress(), $sources->find($order, 'company:' . $entry->getId()));
        self::assertNull($sources->find($order, 'company:' . ($entry->getId() + 1000)));
    }

    private function source(): CompanyAddressSource
    {
        return new CompanyAddressSource(
            $this->service('tmonier_company_accounts.repository.company_member', CompanyMemberRepositoryInterface::class),
            $this->service('tmonier_company_accounts.repository.company_address', CompanyAddressRepositoryInterface::class),
        );
    }

    private function orderOf(?CustomerInterface $customer): OrderInterface
    {
        $order = new Order();
        $order->setCustomer($customer);

        return $order;
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
        $address->setCountryCode('FR');
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
}
