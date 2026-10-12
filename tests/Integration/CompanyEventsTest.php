<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
