<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $customers = $event->getMenu()->getChild('customers');
        if (null === $customers) {
            return;
        }

        $customers
            ->addChild('tmonier_companies', ['route' => 'tmonier_company_accounts_admin_company_index', 'extras' => ['routes' => [
                ['route' => 'tmonier_company_accounts_admin_company_create'],
                ['route' => 'tmonier_company_accounts_admin_company_update'],
                ['route' => 'tmonier_company_accounts_admin_company_member_index'],
                ['route' => 'tmonier_company_accounts_admin_company_member_create'],
                ['route' => 'tmonier_company_accounts_admin_company_member_update'],
            ]]])
            ->setLabel('tmonier_company_accounts.ui.companies')
            ->setLabelAttribute('icon', 'tabler:building')
        ;
    }
}
