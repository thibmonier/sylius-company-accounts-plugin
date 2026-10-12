<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;

class CompanyOrder implements CompanyOrderInterface
{
    protected ?int $id = null;

    protected ?CompanyInterface $company = null;

    protected ?OrderInterface $order = null;

    protected ?\DateTimeInterface $createdAt;

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
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
