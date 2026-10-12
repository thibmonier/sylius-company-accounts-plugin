<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Bridge\OrderHold\CompanyAccountOwnerResolver;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusOrderHoldPlugin\AccountOwner\AccountOwner;
use Tmonier\SyliusOrderHoldPlugin\AccountOwner\AccountOwnerResolverInterface;

/** The order-hold account owner becomes the company (test application: order-hold installed). */
final class AccountOwnerBridgeTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testTheKernelResolverIsDecorated(): void
    {
        self::assertInstanceOf(CompanyAccountOwnerResolver::class, $this->resolver());
    }

    public function testACustomerIsAccountableToTheirActiveCompany(): void
    {
        $company = $this->catalog()->company();
        $member = $this->catalog()->member($company);
        $disabled = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_BUYER, false);
        $ofClosed = $this->catalog()->member($this->catalog()->company('Closed', false));
        $alone = $this->catalog()->customer('alone');

        $this->assertCompanyOwner($company, $this->resolver()->resolveForCustomer($this->customerOf($member)));
        foreach ([$this->customerOf($disabled), $this->customerOf($ofClosed), $alone] as $customer) {
            $owner = $this->resolver()->resolveForCustomer($customer);
            self::assertSame(AccountOwner::TYPE_CUSTOMER, $owner?->getType());
            self::assertSame((string) $this->idOf($customer), $owner->getIdentifier());
        }
    }

    public function testOrdersFollowTheCompanyRecordedAtCheckout(): void
    {
        $company = $this->catalog()->company();
        $customer = $this->catalog()->customer('mover');
        $personal = $this->catalog()->placedOrder($customer);
        $member = $this->catalog()->member($company, $customer);
        $cart = $this->catalog()->placedOrder($customer, checkoutState: 'payment_selected');
        $companyOrder = $this->catalog()->placedOrder($customer);
        $guestOrder = $this->catalog()->placedOrder($customer, asGuest: true);

        // A cart of the member (e.g. payment method eligibility at checkout): the company.
        $this->assertCompanyOwner($company, $this->resolver()->resolveForOrder($cart));
        $this->assertCompanyOwner($company, $this->resolver()->resolveForOrder($companyOrder));
        // Placed before joining, or as a guest with the member's e-mail: the customer.
        self::assertSame(AccountOwner::TYPE_CUSTOMER, $this->resolver()->resolveForOrder($personal)?->getType());
        self::assertSame(AccountOwner::TYPE_CUSTOMER, $this->resolver()->resolveForOrder($guestOrder)?->getType());

        // Leaving the company: placed orders stay the company's, new carts are the customer's.
        $member->setEnabled(false);
        $this->entityManager()->flush();
        $this->assertCompanyOwner($company, $this->resolver()->resolveForOrder($companyOrder));
        self::assertSame(AccountOwner::TYPE_CUSTOMER, $this->resolver()->resolveForOrder($cart)?->getType());
    }

    public function testAnOrderCompletingInTheCurrentFlushIsTheCompanys(): void
    {
        $company = $this->catalog()->company();
        $member = $this->catalog()->member($company);
        $order = $this->catalog()->placedOrder($this->customerOf($member), checkoutState: 'payment_selected');

        // A listener of the complete transition runs before the flush that records the link.
        $this->catalog()->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->assertCompanyOwner($company, $this->resolver()->resolveForOrder($order));
    }

    private function assertCompanyOwner(CompanyInterface $company, ?AccountOwner $owner): void
    {
        self::assertNotNull($owner);
        self::assertSame(CompanyAccountOwnerResolver::TYPE_COMPANY, $owner->getType());
        self::assertSame((string) $company->getId(), $owner->getIdentifier());
        self::assertInstanceOf(CompanyInterface::class, $owner->getSubject());
    }

    private function customerOf(CompanyMemberInterface $member): CustomerInterface
    {
        $customer = $member->getCustomer();
        self::assertNotNull($customer);

        return $customer;
    }

    private function resolver(): AccountOwnerResolverInterface
    {
        return $this->service('tmonier_company_accounts.test.account_owner_resolver', AccountOwnerResolverInterface::class);
    }
}
