<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyEvent;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyEvents;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyMemberEvent;

final class CompanyEventsTest extends KernelTestCase
{
    use IntegrationTestTrait;

    /** @var list<CompanyEvent|CompanyMemberEvent> */
    private array $events = [];

    public function testEventsAreDispatchedAfterTheFlushWithTheirSnapshot(): void
    {
        $this->listen();

        $company = $this->catalog()->company('Evented');
        $member = $this->catalog()->member($company);
        $customerId = $member->getCustomer()?->getId();
        $member->setRole('approver');
        $member->setEnabled(false);
        $company->setName('Renamed');
        $this->entityManager()->flush();
        $companyId = $company->getId();
        $memberId = $member->getId();
        $company->removeMember($member);
        $this->entityManager()->remove($member);
        $this->entityManager()->flush();
        $this->entityManager()->remove($company);
        $this->entityManager()->flush();

        self::assertSame([
            CompanyEvents::COMPANY_CREATED,
            CompanyEvents::MEMBER_ADDED,
            CompanyEvents::COMPANY_UPDATED,
            CompanyEvents::MEMBER_ROLE_CHANGED,
            CompanyEvents::MEMBER_ENABLED_CHANGED,
            CompanyEvents::MEMBER_REMOVED,
            CompanyEvents::COMPANY_DELETED,
        ], array_map(static fn ($event): string => $event->getName(), $this->events));

        [, $added, $updated, $roleChanged, $enabledChanged, $removed, $deleted] = $this->events;
        self::assertInstanceOf(CompanyMemberEvent::class, $added);
        self::assertSame($customerId, $added->getCustomerId());
        self::assertSame($company->getCode(), $added->getCompanyCode());
        self::assertInstanceOf(CompanyEvent::class, $updated);
        self::assertSame(['name'], array_keys($updated->getChanges()));
        self::assertInstanceOf(CompanyMemberEvent::class, $roleChanged);
        self::assertSame(['role' => ['buyer', 'approver']], $roleChanged->getChanges());
        self::assertInstanceOf(CompanyMemberEvent::class, $enabledChanged);
        self::assertSame(['enabled' => [true, false]], $enabledChanged->getChanges());
        self::assertInstanceOf(CompanyMemberEvent::class, $removed);
        self::assertSame([$memberId, $companyId, $customerId], [$removed->getMemberId(), $removed->getCompanyId(), $removed->getCustomerId()]);
        self::assertInstanceOf(CompanyEvent::class, $deleted);
        self::assertSame($companyId, $deleted->getCompanyId());
    }

    public function testTheDefaultCustomerGroupFollowsTheMembership(): void
    {
        $wholesale = $this->catalog()->customerGroup('Wholesale');
        $partners = $this->catalog()->customerGroup('Partners');
        $vip = $this->catalog()->customerGroup('Vip');
        $company = $this->catalog()->company('Grouped', true, $wholesale);

        $plain = $this->catalog()->member($company);
        $ownGroup = $this->catalog()->customer('own-group');
        $ownGroup->setGroup($vip);
        $this->entityManager()->flush();
        $withGroup = $this->catalog()->member($company, $ownGroup);
        self::assertSame($wholesale, $plain->getCustomer()?->getGroup(), 'Given on joining.');
        self::assertSame($vip, $withGroup->getCustomer()?->getGroup(), 'An existing group is kept.');

        $company->setDefaultCustomerGroup($partners);
        $this->entityManager()->flush();
        $this->entityManager()->clear();
        $plain = $this->reload($plain);
        self::assertSame($partners->getId(), $plain->getCustomer()?->getGroup()?->getId(), 'Moved with the company default.');
        self::assertSame($vip->getId(), $this->reload($withGroup)->getCustomer()?->getGroup()?->getId());

        $plain = $this->reload($plain);
        $customer = $plain->getCustomer();
        $plain->getCompany()?->removeMember($plain);
        $this->entityManager()->remove($plain);
        $this->entityManager()->flush();
        $this->entityManager()->clear();
        self::assertNotNull($customer);
        self::assertNull($this->reload($customer)->getGroup(), 'Taken back on leaving.');
    }

    public function testEventsOfAFailedFlushAreNeverDispatched(): void
    {
        $existing = $this->catalog()->company('Taken');
        $this->listen();

        // A concurrent request created the same code: the flush fails, nothing is committed.
        $duplicate = $this->catalog()->companyDraft('Duplicate');
        $duplicate->setCode((string) $existing->getCode());
        $this->entityManager()->persist($duplicate);

        try {
            $this->entityManager()->flush();
            self::fail('The duplicate code should fail the flush.');
        } catch (UniqueConstraintViolationException) {
        }
        $this->service('doctrine', ManagerRegistry::class)->resetManager();

        // The next successful flush of the process dispatches its own events only.
        $next = $this->catalog()->company('Next');

        self::assertSame([CompanyEvents::COMPANY_CREATED], array_map(static fn ($event): string => $event->getName(), $this->events));
        $created = $this->events[0];
        self::assertInstanceOf(CompanyEvent::class, $created);
        self::assertSame($next->getId(), $created->getCompanyId());
    }

    public function testTheCompanyOnlyTakesBackTheGroupItGave(): void
    {
        $wholesale = $this->catalog()->customerGroup('Wholesale');
        $partners = $this->catalog()->customerGroup('Partners');
        $vip = $this->catalog()->customerGroup('Vip');
        $company = $this->catalog()->company('Grouped', true, $wholesale);

        // Already in the default group of the company before joining (set by the merchant).
        $before = $this->catalog()->customer('before');
        $before->setGroup($wholesale);
        $this->entityManager()->flush();
        $alreadyWholesale = $this->catalog()->member($company, $before);
        // Own group, kept on joining.
        $own = $this->catalog()->customer('own');
        $own->setGroup($vip);
        $this->entityManager()->flush();
        $withGroup = $this->catalog()->member($company, $own);
        // Given the company group, then moved to another group by the merchant.
        $moved = $this->catalog()->member($company);
        $movedCustomer = $this->customerOf($moved);
        $movedCustomer->setGroup($partners);
        // Given the company group, then disabled: the group stays.
        $disabled = $this->catalog()->member($company);
        $disabledCustomer = $this->customerOf($disabled);
        $this->entityManager()->flush();
        $disabled->setEnabled(false);
        $this->entityManager()->flush();
        self::assertSame($wholesale, $disabledCustomer->getGroup(), 'Disabling keeps the group.');

        // The company default changes: only the group the company gave moves.
        $company->setDefaultCustomerGroup($vip);
        $this->entityManager()->flush();
        self::assertSame($wholesale, $before->getGroup(), 'Not given by the company: not moved.');
        self::assertSame($partners, $movedCustomer->getGroup());
        self::assertSame($vip, $disabledCustomer->getGroup());

        foreach ([$alreadyWholesale, $withGroup, $moved] as $member) {
            $company->removeMember($member);
            $this->entityManager()->remove($member);
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame($wholesale->getId(), $this->reload($before)->getGroup()?->getId(), 'A group the customer had before joining is kept.');
        self::assertSame($vip->getId(), $this->reload($own)->getGroup()?->getId());
        self::assertSame($partners->getId(), $this->reload($movedCustomer)->getGroup()?->getId(), 'A group changed by the merchant is kept.');
    }

    public function testDeletingTheShopAccountRemovesTheMembership(): void
    {
        $company = $this->catalog()->company('Erased');
        $member = $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $this->catalog()->member($company, null, CompanyMemberInterface::ROLE_ADMIN);
        $this->listen();
        $customer = $member->getCustomer();
        $user = $customer?->getUser();
        self::assertNotNull($user);
        $memberId = $member->getId();

        // What the "Delete" action of the Sylius customer page does.
        $this->entityManager()->remove($user);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame([CompanyEvents::MEMBER_REMOVED], array_map(static fn ($event): string => $event->getName(), $this->events));
        self::assertEquals(0, $this->connection()->fetchOne('SELECT COUNT(*) FROM tmonier_company_member WHERE id = ?', [$memberId]));
        $company = $this->reload($company);
        self::assertCount(1, $company->getMembers());
        self::assertCount(1, $company->getEnabledAdmins());
    }

    public function testDeletingACustomerRemovesTheirMembership(): void
    {
        $company = $this->catalog()->company('Erasure');
        $member = $this->catalog()->member($company);
        $this->catalog()->member($company);
        $this->listen();
        $customer = $member->getCustomer();
        self::assertNotNull($customer);
        $memberId = $member->getId();

        $this->entityManager()->remove($customer);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame([CompanyEvents::MEMBER_REMOVED], array_map(static fn ($event): string => $event->getName(), $this->events));
        self::assertEquals(0, $this->connection()->fetchOne('SELECT COUNT(*) FROM tmonier_company_member WHERE id = ?', [$memberId]));
        self::assertCount(1, $this->reload($company)->getMembers());
    }

    private function customerOf(CompanyMemberInterface $member): CustomerInterface
    {
        $customer = $member->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);

        return $customer;
    }

    private function listen(): void
    {
        $dispatcher = $this->service('event_dispatcher', EventDispatcherInterface::class);
        foreach (CompanyEvents::ALL as $name) {
            $dispatcher->addListener($name, function (CompanyEvent|CompanyMemberEvent $event): void {
                $this->events[] = $event;
            });
        }
    }
}
