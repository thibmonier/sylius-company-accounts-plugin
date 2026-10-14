<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

class Company implements CompanyInterface, \Stringable
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    protected ?string $name = null;

    protected ?string $vatNumber = null;

    protected ?string $registrationNumber = null;

    protected ?CustomerGroupInterface $defaultCustomerGroup = null;

    /** @var Collection<array-key, CompanyMemberInterface> */
    protected Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $this->normalize($vatNumber);
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): void
    {
        $this->registrationNumber = $this->normalize($registrationNumber);
    }

    public function getDefaultCustomerGroup(): ?CustomerGroupInterface
    {
        return $this->defaultCustomerGroup;
    }

    public function setDefaultCustomerGroup(?CustomerGroupInterface $defaultCustomerGroup): void
    {
        $this->defaultCustomerGroup = $defaultCustomerGroup;
    }

    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function hasMember(CompanyMemberInterface $member): bool
    {
        return $this->members->contains($member);
    }

    public function addMember(CompanyMemberInterface $member): void
    {
        if (!$this->hasMember($member)) {
            $this->members->add($member);
            $member->setCompany($this);
        }
    }

    public function removeMember(CompanyMemberInterface $member): void
    {
        if ($this->hasMember($member)) {
            $this->members->removeElement($member);
        }
    }

    public function getEnabledAdmins(): array
    {
        return array_values(array_filter(
            $this->members->toArray(),
            static fn (CompanyMemberInterface $member): bool => $member->isEnabled() && CompanyMemberInterface::ROLE_ADMIN === $member->getRole(),
        ));
    }

    public function __toString(): string
    {
        return (string) ($this->name ?? $this->code);
    }

    private function normalize(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
