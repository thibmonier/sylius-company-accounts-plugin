<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Tests\Tmonier\SyliusCompanyAccountsPlugin\TestCatalog;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * Company A (admin, buyer, approver, a disabled member, an order of the buyer), company B
 * (admin with an order), disabled company C (admin), and a customer without company.
 */
final class CompanyScenario
{
    public readonly CompanyInterface $companyA;

    public readonly CompanyMemberInterface $adminA;

    public readonly CompanyMemberInterface $buyerA;

    public readonly CompanyMemberInterface $approverA;

    public readonly CompanyMemberInterface $disabledA;

    public readonly OrderInterface $orderA;

    public readonly CompanyInterface $companyB;

    public readonly CompanyMemberInterface $adminB;

    public readonly OrderInterface $orderB;

    public readonly CompanyInterface $companyC;

    public readonly CompanyMemberInterface $adminC;

    public readonly CustomerInterface $nonMember;

    public function __construct(TestCatalog $catalog)
    {
        $this->companyA = $catalog->company('Alpha');
        $this->adminA = $catalog->member($this->companyA, $catalog->customer('admin-a', true, 'Alice', 'Admin'), CompanyMemberInterface::ROLE_ADMIN);
        $this->buyerA = $catalog->member($this->companyA, $catalog->customer('buyer-a', true, 'Bob', 'Buyer'), CompanyMemberInterface::ROLE_BUYER);
        $this->approverA = $catalog->member($this->companyA, $catalog->customer('approver-a', true, 'Ava', 'Approver'), CompanyMemberInterface::ROLE_APPROVER);
        $this->disabledA = $catalog->member($this->companyA, $catalog->customer('disabled-a', true, 'Dan', 'Disabled'), CompanyMemberInterface::ROLE_ADMIN, false);
        $this->orderA = $catalog->placedOrder(self::customerOf($this->buyerA));

        $this->companyB = $catalog->company('Beta');
        $this->adminB = $catalog->member($this->companyB, $catalog->customer('admin-b', true, 'Bea', 'Other'), CompanyMemberInterface::ROLE_ADMIN);
        $this->orderB = $catalog->placedOrder(self::customerOf($this->adminB));

        $this->companyC = $catalog->company('Gamma', false);
        $this->adminC = $catalog->member($this->companyC, $catalog->customer('admin-c', true, 'Cid', 'Closed'), CompanyMemberInterface::ROLE_ADMIN);

        $this->nonMember = $catalog->customer('nobody', true, 'Nina', 'Nobody');
    }

    public static function customerOf(CompanyMemberInterface|CustomerInterface $member): CustomerInterface
    {
        $customer = $member instanceof CustomerInterface ? $member : $member->getCustomer();
        \Webmozart\Assert\Assert::notNull($customer);

        return $customer;
    }
}
