<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
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
 * them after the flush. Also applies the default customer group of a company to its members:
 * given when a customer joins (if they have no group), taken back when they leave (if it is
 * still the company group), and moved when the default group of the company changes.
 */
final class CompanyChangeListener implements ResetInterface
{
    /** Changes of these fields alone do not make a `company.updated` event. */
    private const IGNORED_FIELDS = ['createdAt', 'updatedAt'];

    /** @var list<CompanyEvent|CompanyMemberEvent> */
    private array $pending = [];

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
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
                if (null !== $group && null !== $customer && $customer->getGroup() === $group) {
                    $this->changeGroup($entityManager, $customer, null);
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // A listener may flush again: events of that flush are queued after these ones.
        while ([] !== $this->pending) {
            $event = array_shift($this->pending);
            $this->eventDispatcher->dispatch($event, $event->getName());
        }
    }

    public function reset(): void
    {
        $this->pending = [];
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
            if (null === $current || (null !== $previous && $current === $previous)) {
                $this->changeGroup($entityManager, $customer, $next);
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
