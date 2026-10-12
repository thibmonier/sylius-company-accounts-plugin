<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyAccountsPlugin\Bridge\AdminOrderCreation\StaffOrderEligibilityChecker;

/*
 * Loaded only when the tmonier/sylius-admin-order-creation-plugin bundle is enabled: orders
 * created by staff for an active member are company orders.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_company_accounts.company_order_eligibility_checker.staff_order', StaffOrderEligibilityChecker::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->tag('tmonier_company_accounts.company_order_eligibility_checker', ['priority' => 50])
    ;
};
