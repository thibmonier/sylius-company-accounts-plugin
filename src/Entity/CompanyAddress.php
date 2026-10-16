<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Resource\Model\TimestampableTrait;

class CompanyAddress implements CompanyAddressInterface, \Stringable
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?CompanyInterface $company = null;

    protected ?string $label = null;

    protected ?AddressInterface $address = null;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = null === $label ? null : trim($label);
    }

    public function getAddress(): ?AddressInterface
    {
        return $this->address;
    }

    public function setAddress(?AddressInterface $address): void
    {
        $this->address = $address;
    }

    public function __toString(): string
    {
        return (string) $this->label;
    }
}
