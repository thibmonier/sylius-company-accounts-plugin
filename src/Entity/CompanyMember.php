<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

class CompanyMember implements CompanyMemberInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?CompanyInterface $company = null;

    protected ?CustomerInterface $customer = null;

    protected string $role = self::ROLE_BUYER;

    protected bool $customerGroupAssigned = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?CompanyInterface
    {
        return $this->company;
    }

    public function setCompany(?CompanyInterface $company): void
    {
        $this->company = $company;
        $company?->addMember($this);
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return self::ROLE_ADMIN === $this->role;
    }

    public function isCustomerGroupAssigned(): bool
    {
        return $this->customerGroupAssigned;
    }

    public function setCustomerGroupAssigned(bool $customerGroupAssigned): void
    {
        $this->customerGroupAssigned = $customerGroupAssigned;
    }

    public function isActive(): bool
    {
        return $this->enabled && true === $this->company?->isEnabled();
    }
}
