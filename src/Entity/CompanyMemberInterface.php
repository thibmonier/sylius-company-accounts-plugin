<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/**
 * A customer belonging to a company (one company per customer). `approver` is stored for
 * the purchase approval plugin; it grants nothing more than `buyer` in this plugin, except
 * the company orders when listed in `company_orders_roles`.
 */
interface CompanyMemberInterface extends ResourceInterface, ToggleableInterface, TimestampableInterface
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_BUYER = 'buyer';

    public const ROLE_APPROVER = 'approver';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_BUYER, self::ROLE_APPROVER];

    public function getId(): ?int;

    public function getCompany(): ?CompanyInterface;

    public function setCompany(?CompanyInterface $company): void;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    public function getRole(): string;

    public function setRole(string $role): void;

    public function isAdmin(): bool;

    /** The member and its company are both enabled. */
    public function isActive(): bool;
}
