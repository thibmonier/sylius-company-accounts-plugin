<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * Identifiers are captured before the flush: they stay available for a removed member. No
 * personal data is copied: read the customer from its id when you need it (and respect the
 * erasure of a deleted customer).
 */
final class CompanyMemberEvent extends Event
{
    /** @param array<string, array{0: mixed, 1: mixed}> $changes field => [old, new] */
    public function __construct(
        private readonly string $name,
        private readonly CompanyMemberInterface $member,
        private readonly ?int $memberId,
        private readonly ?int $companyId,
        private readonly string $companyCode,
        private readonly ?int $customerId,
        private readonly array $changes = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMember(): CompanyMemberInterface
    {
        return $this->member;
    }

    public function getMemberId(): ?int
    {
        return $this->memberId ?? $this->member->getId();
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId ?? $this->member->getCompany()?->getId();
    }

    public function getCompanyCode(): string
    {
        return $this->companyCode;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getRole(): string
    {
        return $this->member->getRole();
    }

    public function isEnabled(): bool
    {
        return $this->member->isEnabled();
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
