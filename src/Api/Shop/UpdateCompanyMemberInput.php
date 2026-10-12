<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

/** Body of PATCH /api/v2/shop/company/members/{id}: omitted fields keep their value. */
final class UpdateCompanyMemberInput
{
    public ?string $role = null;

    public ?bool $enabled = null;
}
