<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;

/**
 * Identifiers are captured before the flush: they stay available for a deleted company.
 */
final class CompanyEvent extends Event
{
    /** @param array<string, array{0: mixed, 1: mixed}> $changes field => [old, new] (scalars, or ids for relations) */
    public function __construct(
        private readonly string $name,
        private readonly CompanyInterface $company,
        private readonly ?int $companyId,
        private readonly string $companyCode,
        private readonly array $changes = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCompany(): CompanyInterface
    {
        return $this->company;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId ?? $this->company->getId();
    }

    public function getCompanyCode(): string
    {
        return $this->companyCode;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
