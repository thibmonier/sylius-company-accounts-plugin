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

    /** @return string|null the refusal (MemberChangeGuard::ERROR_*), null once the change is saved */
    public function update(CompanyMemberInterface $actor, CompanyMemberInterface $target, string $role, bool $enabled): ?string
    {
        $company = $target->getCompany();
        if (null === $company) {
            return MemberChangeGuard::ERROR_NOT_ALLOWED;
        }

        /** @var string|null $error */
        $error = $this->entityManager->wrapInTransaction(function () use ($actor, $target, $company, $role, $enabled): ?string {
            $this->entityManager->refresh($company, LockMode::PESSIMISTIC_WRITE);
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
