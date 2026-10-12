<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * An order placed by an active member for its company, recorded when the checkout completes.
 * The link survives the member leaving the company: the order stays a company order, and the
 * customer's orders placed before joining never become company orders.
 */
interface CompanyOrderInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getCompany(): ?CompanyInterface;

    public function setCompany(?CompanyInterface $company): void;

    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): void;

    public function getCreatedAt(): ?\DateTimeInterface;
}
