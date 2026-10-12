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
 * them after the flush. Events of a flush that fails are dropped: they are queued in onFlush,
 * and the next flush (or a clear) starts from an empty queue, as postFlush never ran.
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

    /** @var list<CompanyEvent|CompanyMemberEvent> */
    private array $pending = [];

    private bool $dispatching = false;

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        // Left by a flush that failed (no postFlush). A flush made by a listener while the events
        // are dispatched adds its own after the remaining ones.
        if (!$this->dispatching) {
            $this->pending = [];
        }

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof CompanyInterface) {
                $this->pending[] = $this->companyEvent(CompanyEvents::COMPANY_CREATED, $entity);
            } elseif ($entity instanceof CompanyMemberInterface) {
                $this->pending[] = $this->memberEvent(CompanyEvents::MEMBER_ADDED, $entity);
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
                    $this->pending[] = $this->companyEvent(CompanyEvents::COMPANY_UPDATED, $entity, $changes);
                }
                $groupChange = $unitOfWork->getEntityChangeSet($entity)['defaultCustomerGroup'] ?? null;
                if (null !== $groupChange) {
                    $this->moveDefaultGroup($entityManager, $entity, self::group($groupChange[0]), self::group($groupChange[1]));
                }
            } elseif ($entity instanceof CompanyMemberInterface) {
                $changes = self::changes($unitOfWork, $entity);
                if (isset($changes['role'])) {
                    $this->pending[] = $this->memberEvent(CompanyEvents::MEMBER_ROLE_CHANGED, $entity, ['role' => $changes['role']]);
                }
                if (isset($changes['enabled'])) {
                    $this->pending[] = $this->memberEvent(CompanyEvents::MEMBER_ENABLED_CHANGED, $entity, ['enabled' => $changes['enabled']]);
                }
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof CompanyInterface) {
                $this->pending[] = $this->companyEvent(CompanyEvents::COMPANY_DELETED, $entity);
            } elseif ($entity instanceof CompanyMemberInterface) {
                $this->pending[] = $this->memberEvent(CompanyEvents::MEMBER_REMOVED, $entity);
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
        if (!$this->dispatching) {
            $this->pending = [];
        }
    }

    public function reset(): void
    {
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
