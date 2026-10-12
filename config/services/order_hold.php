<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Bridge\OrderHold\CompanyAccountOwnerResolver;

/*
 * Loaded only when tmonier/sylius-order-hold-plugin is installed. The decoration is ignored when
 * its bundle is not enabled.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_company_accounts.order_hold.account_owner_resolver', CompanyAccountOwnerResolver::class)
        ->decorate('tmonier_order_hold.resolver.account_owner', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service('.inner'),
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.repository.company_order'),
            service('doctrine.orm.entity_manager'),
            service('tmonier_company_accounts.company_order_eligibility_checker'),
        ])
    ;
};
