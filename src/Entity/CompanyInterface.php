<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

interface CompanyInterface extends ResourceInterface, CodeAwareInterface, ToggleableInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    /** Intra-community VAT number, as entered (not checked against VIES). */
    public function getVatNumber(): ?string;

    public function setVatNumber(?string $vatNumber): void;

    /** Company registration number (SIREN/SIRET, Companies House number, KRS, ...). */
    public function getRegistrationNumber(): ?string;

    public function setRegistrationNumber(?string $registrationNumber): void;

    /** Customer group given to customers when they join the company. */
    public function getDefaultCustomerGroup(): ?CustomerGroupInterface;

    public function setDefaultCustomerGroup(?CustomerGroupInterface $defaultCustomerGroup): void;

    /** @return Collection<array-key, CompanyMemberInterface> */
    public function getMembers(): Collection;

    public function hasMember(CompanyMemberInterface $member): bool;

    public function addMember(CompanyMemberInterface $member): void;

    public function removeMember(CompanyMemberInterface $member): void;

    /** @return list<CompanyMemberInterface> enabled members with the admin role */
    public function getEnabledAdmins(): array;
}
