<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyAccountsPlugin\Bridge\AdminOrderCreation\CompanyAddressSource;

/*
 * Loaded only when the tmonier/sylius-admin-order-creation-plugin bundle (1.1+, which defines the
 * address sources) is enabled: the shared addresses of the company are offered first at the
 * address step of the orders created by staff.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_company_accounts.admin_order_address_source.company', CompanyAddressSource::class)
        ->args([
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.repository.company_address'),
        ])
        ->tag('tmonier_admin_order.address_source', ['priority' => 100])
    ;
};
