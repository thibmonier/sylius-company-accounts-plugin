<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Bridge\AdminOrderCreation;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusAdminOrderCreationPlugin\Address\OrderAddressSourceInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

/**
 * Registered only when tmonier/sylius-admin-order-creation-plugin (1.1+) is enabled: the shared
 * address book of the company of the customer of the order is offered at the address step. Only
 * the book of the company the customer is an active member of is ever returned, so it is also the
 * whitelist a submitted reference is checked against.
 */
final readonly class CompanyAddressSource implements OrderAddressSourceInterface
{
    public const CODE = 'company';

    public function __construct(
        private CompanyMemberRepositoryInterface $memberRepository,
        private CompanyAddressRepositoryInterface $addressRepository,
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'tmonier_company_accounts.ui.checkout_addresses';
    }

    public function getAddresses(OrderInterface $order): array
    {
        $customer = $order->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            return [];
        }

        $member = $this->memberRepository->findOneByCustomer($customer);
        $company = $member?->getCompany();
        if (!$member instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface || !$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || !$member->isActive()) {
            return [];
        }

        $addresses = [];
        foreach ($this->addressRepository->findByCompany($company) as $entry) {
            $id = $entry->getId();
            $address = $entry->getAddress();
            if (null !== $id && null !== $address) {
                $addresses[$id] = $address;
            }
        }

        return $addresses;
    }
}
