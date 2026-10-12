<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;

/**
 * Demo company with an admin, a buyer and an approver (verified shop users, password
 * `sylius`), and orders placed by the admin and the buyer (recorded as company orders by the
 * checkout listener). Run it after the Sylius `order` fixture (shipping and payment methods).
 */
final class CompanyFixture extends AbstractFixture
{
    private const MEMBERS = [
        CompanyMemberInterface::ROLE_ADMIN => ['Camille', 'Durand'],
        CompanyMemberInterface::ROLE_BUYER => ['Hugo', 'Martin'],
        CompanyMemberInterface::ROLE_APPROVER => ['Lea', 'Bernard'],
    ];

    /**
     * @param FactoryInterface<CompanyInterface> $companyFactory
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param FactoryInterface<ShopUserInterface> $shopUserFactory
     * @param ExampleFactoryInterface<OrderInterface> $orderExampleFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $companyFactory,
        private readonly CompanyMemberFactoryInterface $memberFactory,
        private readonly FactoryInterface $customerFactory,
        private readonly FactoryInterface $shopUserFactory,
        private readonly ExampleFactoryInterface $orderExampleFactory,
    ) {
    }

    public function getName(): string
    {
        return 'tmonier_company_accounts';
    }

    /** @param array{code: string, name: string, vat_number: string|null, registration_number: string|null, customer_group: string|null, email_domain: string, orders_per_member: int, channel: string|null} $options */
    public function load(array $options): void
    {
        $company = $this->companyFactory->createNew();
        $company->setCode($options['code']);
        $company->setName($options['name']);
        $company->setVatNumber($options['vat_number']);
        $company->setRegistrationNumber($options['registration_number']);
        if (null !== $options['customer_group']) {
            $group = $this->entityManager->getRepository(CustomerGroupInterface::class)->findOneBy(['code' => $options['customer_group']]);
            $company->setDefaultCustomerGroup($group instanceof CustomerGroupInterface ? $group : null);
        }
        $this->entityManager->persist($company);

        $customers = [];
        foreach (self::MEMBERS as $role => [$firstName, $lastName]) {
            $customers[$role] = $this->customer(sprintf('%s@%s', $role, $options['email_domain']), $firstName, $lastName);
            $company->addMember($this->memberFactory->createForCustomer($company, $customers[$role], $role));
        }
        $this->entityManager->flush();

        $channel = null === $options['channel'] ? null : $this->entityManager->getRepository(ChannelInterface::class)->findOneBy(['code' => $options['channel']]);
        $channel ??= $this->entityManager->getRepository(ChannelInterface::class)->findOneBy(['enabled' => true], ['id' => 'ASC']);
        if (!$channel instanceof ChannelInterface || 0 === $options['orders_per_member']) {
            return;
        }
        foreach (array_intersect_key($customers, [CompanyMemberInterface::ROLE_ADMIN => true, CompanyMemberInterface::ROLE_BUYER => true]) as $customer) {
            for ($i = 0; $i < $options['orders_per_member']; ++$i) {
                $order = $this->orderExampleFactory->create(['channel' => $channel, 'customer' => $customer]);
                $this->entityManager->persist($order);
                $this->entityManager->flush();
            }
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('code')->defaultValue('ACME_INDUSTRIES')->cannotBeEmpty()->end()
                ->scalarNode('name')->defaultValue('Acme Industries')->cannotBeEmpty()->end()
                ->scalarNode('vat_number')->defaultValue('FR40123456824')->end()
                ->scalarNode('registration_number')->defaultValue('123 456 824')->end()
                ->scalarNode('customer_group')->defaultValue('wholesale')->end()
                ->scalarNode('email_domain')->defaultValue('acme-industries.example.com')->cannotBeEmpty()->end()
                ->integerNode('orders_per_member')->defaultValue(2)->min(0)->end()
                ->scalarNode('channel')->defaultNull()->end()
            ->end()
        ;
    }

    private function customer(string $email, string $firstName, string $lastName): CustomerInterface
    {
        $customer = $this->entityManager->getRepository(CustomerInterface::class)->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$customer instanceof CustomerInterface) {
            $customer = $this->customerFactory->createNew();
            $customer->setEmail($email);
            $customer->setFirstName($firstName);
            $customer->setLastName($lastName);
            $this->entityManager->persist($customer);
        }
        $user = $customer->getUser();
        if (!$user instanceof ShopUserInterface) {
            $user = $this->shopUserFactory->createNew();
            $user->setPlainPassword('sylius');
            $customer->setUser($user);
        }
        $user->setEnabled(true);
        // The demo channel requires a verified account to sign in.
        $user->setVerifiedAt(new \DateTime());

        return $customer;
    }
}
