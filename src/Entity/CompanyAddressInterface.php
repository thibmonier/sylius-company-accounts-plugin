<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

/**
 * An entry of the address book shared by the members of a company: a label ("Lyon plant") and a
 * Sylius address, owned by the entry (removed with it). Members pick one as the shipping address
 * of their checkout, which copies it to the order: later changes never alter placed orders.
 */
interface CompanyAddressInterface extends ResourceInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getCompany(): ?CompanyInterface;

    public function setCompany(?CompanyInterface $company): void;

    public function getLabel(): ?string;

    public function setLabel(?string $label): void;

    public function getAddress(): ?AddressInterface;

    public function setAddress(?AddressInterface $address): void;
}
