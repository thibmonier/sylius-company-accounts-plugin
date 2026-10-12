<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyEvent;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyEvents;
use Tmonier\SyliusCompanyAccountsPlugin\Event\CompanyMemberEvent;

/**
 * Derives the company events from the unit of work (whatever made the change) and dispatches
 * them after the flush. Events of a flush that fails are dropped: they are collected per flush
 * in onFlush and only queued for dispatch by the postFlush of that same flush, which never runs
 * when the flush fails (also for a flush made, and failing, inside an event subscriber).
 *
 * Also applies the default customer group of a company to its members: given when a customer
 * with no group joins (CompanyMember::customerGroupAssigned), and only that group is taken back
 * when they leave or moved when the company default changes. A group set by the merchant, before
 * or after joining, is never touched.
 */
final class CompanyChangeListener implements ResetInterface
{
    /** Changes of these fields alone do not make a `company.updated` event. */
    private const IGNORED_FIELDS = ['createdAt', 'updatedAt', 'customerGroupAssigned'];

    /**
     * Events of the flush running now, queued by its postFlush.
     *
     * @var list<CompanyEvent|CompanyMemberEvent>
     */
    private array $flushing = [];

    /**
     * Events of completed flushes, waiting to be dispatched.
     *
     * @var list<CompanyEvent|CompanyMemberEvent>
     */
    private array $pending = [];

    private bool $dispatching = false;

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        // Left by a flush that failed (no postFlush).
        $this->flushing = [];

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof CompanyInterface) {
                $this->flushing[] = $this->companyEvent(CompanyEvents::COMPANY_CREATED, $entity);
            } elseif ($entity instanceof CompanyMemberInterface) {
                $this->flushing[] = $this->memberEvent(CompanyEvents::MEMBER_ADDED, $entity);
                $group = $entity->getCompany()?->getDefaultCustomerGroup();
                $customer = $entity->getCustomer();
                if (null !== $group && null !== $customer && null === $customer->getGroup()) {
                    $this->changeGroup($entityManager, $customer, $group);
                    $this->markGroupAssigned($entityManager, $entity, true);
                }
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof CompanyInterface) {
                $changes = self::changes($unitOfWork, $entity);
                if ([] !== $changes) {
                    $this->flushing[] = $this->companyEvent(CompanyEvents::COMPANY_UPDATED, $entity, $changes);
                }
                $groupChange = $unitOfWork->getEntityChangeSet($entity)['defaultCustomerGroup'] ?? null;
                if (null !== $groupChange) {
                    $this->moveDefaultGroup($entityManager, $entity, self::group($groupChange[0]), self::group($groupChange[1]));
                }
            } elseif ($entity instanceof CompanyMemberInterface) {
                $changes = self::changes($unitOfWork, $entity);
                if (isset($changes['role'])) {
                    $this->flushing[] = $this->memberEvent(CompanyEvents::MEMBER_ROLE_CHANGED, $entity, ['role' => $changes['role']]);
                }
                if (isset($changes['enabled'])) {
                    $this->flushing[] = $this->memberEvent(CompanyEvents::MEMBER_ENABLED_CHANGED, $entity, ['enabled' => $changes['enabled']]);
                }
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof CompanyInterface) {
                $this->flushing[] = $this->companyEvent(CompanyEvents::COMPANY_DELETED, $entity);
            } elseif ($entity instanceof CompanyMemberInterface) {
                $this->flushing[] = $this->memberEvent(CompanyEvents::MEMBER_REMOVED, $entity);
                $group = $entity->getCompany()?->getDefaultCustomerGroup();
                $customer = $entity->getCustomer();
                if ($entity->isCustomerGroupAssigned() && null !== $group && null !== $customer && $customer->getGroup() === $group) {
                    $this->changeGroup($entityManager, $customer, null);
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        array_push($this->pending, ...$this->flushing);
        $this->flushing = [];
        // A flush made by a subscriber while the events are dispatched: its events are
        // dispatched after the remaining ones by the loop below.
        if ($this->dispatching) {
            return;
        }

        // A listener may flush again: events of that flush are queued after these ones.
        $this->dispatching = true;

        try {
            while ([] !== $this->pending) {
                $event = array_shift($this->pending);
                $this->eventDispatcher->dispatch($event, $event->getName());
            }
        } finally {
            $this->dispatching = false;
            $this->pending = [];
        }
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->flushing = [];
        if (!$this->dispatching) {
            $this->pending = [];
        }
    }

    public function reset(): void
    {
        $this->flushing = [];
        $this->pending = [];
        $this->dispatching = false;
    }

    private function moveDefaultGroup(EntityManagerInterface $entityManager, CompanyInterface $company, ?CustomerGroupInterface $previous, ?CustomerGroupInterface $next): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($company->getMembers() as $member) {
            $customer = $member->getCustomer();
            if (null === $customer || $unitOfWork->isScheduledForDelete($member)) {
                continue;
            }
            $current = $customer->getGroup();
            if ($member->isCustomerGroupAssigned() && null !== $previous && $current === $previous) {
                $this->changeGroup($entityManager, $customer, $next);
                $this->markGroupAssigned($entityManager, $member, null !== $next);
            } elseif (null === $current && null !== $next) {
                $this->changeGroup($entityManager, $customer, $next);
                $this->markGroupAssigned($entityManager, $member, true);
            }
        }
    }

    private function changeGroup(EntityManagerInterface $entityManager, CustomerInterface $customer, ?CustomerGroupInterface $group): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        if ($customer->getGroup() === $group || $unitOfWork->isScheduledForDelete($customer)) {
            return;
        }

        $customer->setGroup($group);
        if ($unitOfWork->isScheduledForInsert($customer) || UnitOfWork::STATE_MANAGED === $unitOfWork->getEntityState($customer)) {
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($customer::class), $customer);
        }
    }

    private function markGroupAssigned(EntityManagerInterface $entityManager, CompanyMemberInterface $member, bool $assigned): void
    {
        if ($member->isCustomerGroupAssigned() === $assigned) {
            return;
        }
        $member->setCustomerGroupAssigned($assigned);
        $unitOfWork = $entityManager->getUnitOfWork();
        if ($unitOfWork->isScheduledForInsert($member) || UnitOfWork::STATE_MANAGED === $unitOfWork->getEntityState($member)) {
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($member::class), $member);
        }
    }

    /** @param array<string, array{0: mixed, 1: mixed}> $changes */
    private function companyEvent(string $name, CompanyInterface $company, array $changes = []): CompanyEvent
    {
        return new CompanyEvent($name, $company, $company->getId(), (string) $company->getCode(), $changes);
    }

    /** @param array<string, array{0: mixed, 1: mixed}> $changes */
    private function memberEvent(string $name, CompanyMemberInterface $member, array $changes = []): CompanyMemberEvent
    {
        $customerId = $member->getCustomer()?->getId();

        return new CompanyMemberEvent(
            $name,
            $member,
            $member->getId(),
            $member->getCompany()?->getId(),
            (string) $member->getCompany()?->getCode(),
            is_int($customerId) ? $customerId : null,
            $changes,
        );
    }

    /** @return array<string, array{0: mixed, 1: mixed}> scalars as is, entities as ids */
    private static function changes(UnitOfWork $unitOfWork, object $entity): array
    {
        $changes = [];
        foreach ($unitOfWork->getEntityChangeSet($entity) as $field => $change) {
            if (in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }
            [$old, $new] = [self::value($change[0] ?? null), self::value($change[1] ?? null)];
            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    private static function value(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }

        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : $value;
    }

    private static function group(mixed $group): ?CustomerGroupInterface
    {
        return $group instanceof CustomerGroupInterface ? $group : null;
    }
}
