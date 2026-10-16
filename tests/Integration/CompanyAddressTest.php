<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;

final class CompanyAddressTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testAddressesAreListedByLabelAndScopedToTheirCompany(): void
    {
        $company = $this->catalog()->company('Alpha');
        $other = $this->catalog()->company('Beta');
        $zeta = $this->persisted($company, 'Zeta');
        $alpha = $this->persisted($company, 'Alpha site');
        $foreign = $this->persisted($other, 'Beta site');
        $repository = $this->repository();

        self::assertSame([$alpha->getId(), $zeta->getId()], array_map(static fn (CompanyAddressInterface $a): ?int => $a->getId(), $repository->findByCompany($company)));
        self::assertSame($alpha->getId(), $repository->findOneByIdAndCompany((int) $alpha->getId(), $company)?->getId());
        self::assertNotInstanceOf(CompanyAddressInterface::class, $repository->findOneByIdAndCompany((int) $foreign->getId(), $company));
    }

    public function testRemovingACompanyRemovesItsAddressesAndTheirSyliusAddresses(): void
    {
        $company = $this->catalog()->company('Doomed');
        $entry = $this->persisted($company, 'Lyon plant');
        $addressId = $entry->getAddress()?->getId();
        $entryId = $entry->getId();

        $this->entityManager()->remove($company);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertNull($this->entityManager()->getRepository(CompanyAddressInterface::class)->find($entryId));
        self::assertNull($this->entityManager()->getRepository(AddressInterface::class)->find($addressId));
    }

    public function testALabelAndAValidAddressAreRequired(): void
    {
        $validator = $this->service('validator', ValidatorInterface::class);
        $entry = $this->build($this->catalog()->company('Valid'), '');
        $entry->getAddress()?->setPostcode(null);

        $paths = array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate($entry, null, ['sylius'])));

        self::assertContains('label', $paths);
        self::assertContains('address.postcode', $paths);
        self::assertCount(0, $validator->validate($this->build($this->catalog()->company('Fine'), 'Lyon plant'), null, ['sylius']));
    }

    private function build(CompanyInterface $company, string $label): CompanyAddressInterface
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

        return $entry;
    }

    private function persisted(CompanyInterface $company, string $label): CompanyAddressInterface
    {
        $entry = $this->build($company, $label);
        $this->entityManager()->persist($entry);
        $this->entityManager()->flush();

        return $entry;
    }

    private function repository(): CompanyAddressRepositoryInterface
    {
        return $this->service('tmonier_company_accounts.repository.company_address', CompanyAddressRepositoryInterface::class);
    }
}
