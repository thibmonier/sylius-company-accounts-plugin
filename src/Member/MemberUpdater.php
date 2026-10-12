<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;

/**
 * Member changes made in the shop (account pages, shop API). The company row is locked, the
 * actor and the target are re-read and the enabled admins are counted in the database before
 * the guard runs: two admins demoting each other at the same time cannot leave the company
 * without an admin (the second change waits for the first one and is then refused).
 */
final class MemberUpdater
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly MemberChangeGuard $guard,
    ) {
    }

    /** The target was deleted or moved to another company since it was loaded (404). */
    public const ERROR_TARGET_NOT_FOUND = 'tmonier_company_accounts.member.not_found';

    /**
     * @return string|null the refusal (MemberChangeGuard::ERROR_*, or ERROR_TARGET_NOT_FOUND),
     *                     null once the change is saved
     */
    public function update(CompanyMemberInterface $actor, CompanyMemberInterface $target, string $role, bool $enabled): ?string
    {
        $company = $target->getCompany();
        $actorId = $actor->getId();
        $targetId = $target->getId();
        if (null === $company || null === $targetId || !$this->entityManager->contains($target)) {
            return self::ERROR_TARGET_NOT_FOUND;
        }
        if (null === $actorId || !$this->entityManager->contains($actor)) {
            return MemberChangeGuard::ERROR_NOT_ALLOWED;
        }

        /** @var string|null $error */
        $error = $this->entityManager->wrapInTransaction(function () use ($actor, $target, $actorId, $targetId, $company, $role, $enabled): ?string {
            $this->entityManager->refresh($company, LockMode::PESSIMISTIC_WRITE);
            // Deleted or moved meanwhile (refresh() would keep the stale data of a deleted row).
            $present = $this->memberRepository->findIdsInCompany($company, array_values(array_unique([$actorId, $targetId])));
            if (!in_array($targetId, $present, true)) {
                return self::ERROR_TARGET_NOT_FOUND;
            }
            if (!in_array($actorId, $present, true)) {
                return MemberChangeGuard::ERROR_NOT_ALLOWED;
            }
            $this->entityManager->refresh($actor);
            if ($target !== $actor) {
                $this->entityManager->refresh($target);
            }
            $error = $this->guard->check($actor, $target, $role, $enabled, $this->memberRepository->countEnabledAdmins($company));
            if (null === $error) {
                $target->setRole($role);
                $target->setEnabled($enabled);
            }

            return $error;
        });

        return $error;
    }
}
