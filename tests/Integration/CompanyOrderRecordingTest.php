<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;

/** The company of an order is recorded in the flush that completes the checkout. */
final class CompanyOrderRecordingTest extends KernelTestCase
{
    use IntegrationTestTrait;

    public function testAnOrderOfAnActiveMemberBecomesACompanyOrder(): void
    {
        $company = $this->catalog()->company();
        $member = $this->catalog()->member($company);

        $order = $this->catalog()->placedOrder($this->customerOf($member));

        self::assertSame($company->getId(), $this->repository()->findOneByOrder($order)?->getCompany()?->getId());
    }

    public function testCartsAndCheckoutStepsAreNotRecorded(): void
    {
        $member = $this->catalog()->member($this->catalog()->company());

        $order = $this->catalog()->placedOrder($this->customerOf($member), checkoutState: 'payment_selected');

        self::assertNull($this->repository()->findOneByOrder($order));
        $this->catalog()->apply($order, 'complete');
        $this->entityManager()->flush();
        self::assertNotNull($this->repository()->findOneByOrder($order));
    }

    public function testOrdersOutsideAnActiveMembershipAreNotCompanyOrders(): void
    {
        $company = $this->catalog()->company();
        $disabledMember = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_BUYER, false);
        $closedCompany = $this->catalog()->company('Closed', false);
        $memberOfClosed = $this->catalog()->member($closedCompany);
        $activeMember = $this->catalog()->member($company);

        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->customerOf($disabledMember))), 'disabled member');
        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->customerOf($memberOfClosed))), 'disabled company');
        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->catalog()->customer('alone'))), 'no company');
        // Sylius attaches the existing customer to a guest checkout typed with their e-mail.
        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->customerOf($activeMember), asGuest: true)), 'guest checkout with the e-mail of a member');
    }

    public function testAnOrderCreatedByStaffForAnActiveMemberIsACompanyOrder(): void
    {
        $company = $this->catalog()->company();
        $member = $this->catalog()->member($company);
        $disabled = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_BUYER, false);

        $order = $this->catalog()->placedOrder($this->customerOf($member), byStaff: true);

        self::assertTrue($order->isCreatedByGuest(), 'Staff orders are not placed by the signed-in customer.');
        self::assertSame($company->getId(), $this->repository()->findOneByOrder($order)?->getCompany()?->getId());
        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->customerOf($disabled), byStaff: true)), 'disabled member');
        self::assertNull($this->repository()->findOneByOrder($this->catalog()->placedOrder($this->catalog()->customer('alone'), byStaff: true)), 'no company');
    }

    public function testTheLinkSurvivesTheMemberLeavingAndPersonalOrdersStayPersonal(): void
    {
        $company = $this->catalog()->company();
        $customer = $this->catalog()->customer('mover');
        $before = $this->catalog()->placedOrder($customer);
        $member = $this->catalog()->member($company, $customer);
        $during = $this->catalog()->placedOrder($customer);

        $member = $this->reload($member);
        $member->getCompany()?->removeMember($member);
        $this->entityManager()->remove($member);
        $this->entityManager()->flush();
        $after = $this->catalog()->placedOrder($this->reload($customer));

        self::assertNull($this->repository()->findOneByOrder($this->reload($before)));
        self::assertSame($company->getId(), $this->repository()->findOneByOrder($this->reload($during))?->getCompany()?->getId());
        self::assertNull($this->repository()->findOneByOrder($this->reload($after)));
    }

    public function testOrdersCompletedOnInsertAreRecordedOnce(): void
    {
        $member = $this->catalog()->member($this->catalog()->company());

        [$order] = $this->catalog()->completedOrders($this->customerOf($member), 1);
        $order->setNotes('touched');
        $this->entityManager()->flush();

        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM tmonier_company_order WHERE order_id = ?', [$order->getId()]);
        self::assertEquals(1, $count);
    }

    private function customerOf(CompanyMemberInterface $member): CustomerInterface
    {
        $customer = $member->getCustomer();
        self::assertNotNull($customer);

        return $customer;
    }

    private function repository(): CompanyOrderRepositoryInterface
    {
        return $this->service('tmonier_company_accounts.repository.company_order', CompanyOrderRepositoryInterface::class);
    }
}
