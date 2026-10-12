<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/** GET /api/v2/shop/company: the company of the signed-in member. */
#[ApiResource(
    shortName: 'ShopCompany',
    operations: [
        new Get(
            uriTemplate: '/shop/company',
            uriVariables: [],
            description: 'The company of the signed-in customer: 404 without membership, 403 when the membership or the company is disabled.',
            security: "is_granted('ROLE_USER')",
            provider: CompanyProvider::class,
            name: 'tmonier_company_accounts_shop_company_get',
        ),
    ],
)]
final class CompanyView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $vatNumber,
        public readonly ?string $registrationNumber,
        /** Role of the signed-in member: admin, buyer or approver. */
        public readonly string $role,
        public readonly int $membersCount,
        public readonly bool $canManageMembers,
        public readonly bool $canViewCompanyOrders,
    ) {
    }
}
