<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/**
 * A colleague as seen by a member: name and role, never the e-mail or phone number.
 */
#[ApiResource(
    shortName: 'ShopCompanyMember',
    operations: [
        new GetCollection(
            uriTemplate: '/shop/company/members',
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            provider: CompanyMemberProvider::class,
            name: 'tmonier_company_accounts_shop_company_members_get',
        ),
        new Get(
            uriTemplate: '/shop/company/members/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_USER')",
            provider: CompanyMemberProvider::class,
            name: 'tmonier_company_accounts_shop_company_member_get',
        ),
        new Patch(
            uriTemplate: '/shop/company/members/{id}',
            requirements: ['id' => '\d+'],
            description: 'Company admins change the role or the status of another member; the last enabled admin cannot be demoted or disabled (422).',
            security: "is_granted('ROLE_USER')",
            input: UpdateCompanyMemberInput::class,
            read: false,
            processor: UpdateCompanyMemberProcessor::class,
            name: 'tmonier_company_accounts_shop_company_member_patch',
        ),
    ],
)]
final class CompanyMemberView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly int $id,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $role,
        public readonly bool $enabled,
        /** The signed-in member themselves. */
        public readonly bool $me,
    ) {
    }

    public static function fromMember(CompanyMemberInterface $member, CompanyMemberInterface $viewer): self
    {
        $customer = $member->getCustomer();

        return new self(
            (int) $member->getId(),
            $customer?->getFirstName(),
            $customer?->getLastName(),
            $member->getRole(),
            $member->isEnabled(),
            $member->getId() === $viewer->getId(),
        );
    }
}
