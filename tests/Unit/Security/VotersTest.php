<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\Order;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\Company;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMember;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrder;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyVoter;
use Tmonier\SyliusCompanyAccountsPlugin\Security\OrderVoter;

final class VotersTest extends TestCase
{
    private ?CompanyMemberInterface $current = null;

    private ?CustomerInterface $currentCustomer = null;

    public function testCompanyPermissionsByRoleAndStatus(): void
    {
        $company = self::company(1);
        $other = self::company(2);
        $voter = $this->companyVoter();

        foreach ([
            // role, member enabled, company enabled => [VIEW, MANAGE, ORDERS]
            ['admin', true, true, [true, true, true]],
            ['approver', true, true, [true, false, true]],
            ['buyer', true, true, [true, false, false]],
            ['admin', false, true, [false, false, false]],
            ['admin', true, false, [false, false, false]],
        ] as [$role, $memberEnabled, $companyEnabled, $expected]) {
            $company->setEnabled($companyEnabled);
            $this->current = self::member($company, $role, $memberEnabled);
            $label = sprintf('%s, member %s, company %s', $role, var_export($memberEnabled, true), var_export($companyEnabled, true));
            self::assertSame($expected, [
                $this->grants($voter, CompanyPermissions::COMPANY_VIEW, $company),
                $this->grants($voter, CompanyPermissions::COMPANY_MANAGE_MEMBERS, $company),
                $this->grants($voter, CompanyPermissions::ORDER_VIEW_COMPANY, $company),
            ], $label);
            self::assertFalse($this->grants($voter, CompanyPermissions::COMPANY_VIEW, $other), $label . ', other company');
        }

        $this->current = null;
        self::assertFalse($this->grants($voter, CompanyPermissions::COMPANY_VIEW, $company), 'non-member');
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new \stdClass(), [CompanyPermissions::COMPANY_VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), $company, ['ROLE_USER']));
    }

    public function testTheCompanyOrdersRolesAreConfigurable(): void
    {
        $company = self::company(1);
        $this->current = self::member($company, 'buyer');

        self::assertTrue($this->grants($this->companyVoter(['admin', 'buyer']), CompanyPermissions::ORDER_VIEW_COMPANY, $company));
        self::assertFalse($this->grants($this->companyVoter(['admin']), CompanyPermissions::ORDER_VIEW_COMPANY, $company));
    }

    public function testOrderPermissions(): void
    {
        $company = self::company(1);
        $other = self::company(2);
        $owner = self::customer(10);
        $colleague = self::customer(11);
        $companyOrder = new Order();
        $companyOrder->setCustomer($owner);
        $foreignOrder = new Order();
        $foreignOrder->setCustomer(self::customer(12));
        $personalOrder = new Order();
        $personalOrder->setCustomer($colleague);
        $links = [spl_object_id($companyOrder) => $company, spl_object_id($foreignOrder) => $other];
        $voter = new OrderVoter($this->resolver(), $this->companyOrders($links), $this->companyVoter());

        $this->currentCustomer = $colleague;
        $this->current = self::member($company, 'approver');
        self::assertTrue($this->grants($voter, CompanyPermissions::ORDER_VIEW_COMPANY, $companyOrder));
        self::assertFalse($this->grants($voter, CompanyPermissions::ORDER_VIEW_COMPANY, $foreignOrder));
        self::assertFalse($this->grants($voter, CompanyPermissions::ORDER_VIEW_COMPANY, $personalOrder), 'Not a company order.');
        self::assertFalse($this->grants($voter, CompanyPermissions::ORDER_VIEW_OWN, $companyOrder));
        self::assertTrue($this->grants($voter, CompanyPermissions::ORDER_VIEW_OWN, $personalOrder));

        $this->current = self::member($company, 'buyer');
        self::assertFalse($this->grants($voter, CompanyPermissions::ORDER_VIEW_COMPANY, $companyOrder));

        $this->currentCustomer = null;
        $this->current = null;
        self::assertFalse($this->grants($voter, CompanyPermissions::ORDER_VIEW_OWN, $personalOrder), 'visitor');
    }

    private function grants(VoterInterface $voter, string $attribute, object $subject): bool
    {
        return VoterInterface::ACCESS_GRANTED === $voter->vote(new NullToken(), $subject, [$attribute]);
    }

    /** @param list<string> $roles */
    private function companyVoter(array $roles = ['admin', 'approver']): CompanyVoter
    {
        return new CompanyVoter($this->resolver(), $roles);
    }

    private function resolver(): CurrentMemberResolverInterface
    {
        return new class($this) implements CurrentMemberResolverInterface {
            public function __construct(private readonly VotersTest $test)
            {
            }

            public function getCustomer(?TokenInterface $token = null): ?CustomerInterface
            {
                return $this->test->currentCustomer();
            }

            public function getMember(?TokenInterface $token = null): ?CompanyMemberInterface
            {
                return $this->test->currentMember();
            }
        };
    }

    public function currentCustomer(): ?CustomerInterface
    {
        return $this->currentCustomer;
    }

    public function currentMember(): ?CompanyMemberInterface
    {
        return $this->current;
    }

    /** @param array<int, CompanyInterface> $links */
    private function companyOrders(array $links): CompanyOrderRepositoryInterface
    {
        $repository = $this->createMock(CompanyOrderRepositoryInterface::class);
        $repository->method('findOneByOrder')->willReturnCallback(static function (Order $order) use ($links): ?CompanyOrder {
            $company = $links[spl_object_id($order)] ?? null;
            if (null === $company) {
                return null;
            }
            $link = new CompanyOrder();
            $link->setCompany($company);
            $link->setOrder($order);

            return $link;
        });

        return $repository;
    }

    private static function company(int $id): CompanyInterface
    {
        $company = new Company();
        (new \ReflectionProperty(Company::class, 'id'))->setValue($company, $id);

        return $company;
    }

    private static function member(CompanyInterface $company, string $role, bool $enabled = true): CompanyMemberInterface
    {
        $member = new CompanyMember();
        $member->setRole($role);
        $member->setEnabled($enabled);
        $member->setCompany($company);

        return $member;
    }

    private static function customer(int $id): CustomerInterface
    {
        $customer = new Customer();
        (new \ReflectionProperty(Customer::class, 'id'))->setValue($customer, $id);

        return $customer;
    }
}
