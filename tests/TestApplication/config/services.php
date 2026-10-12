<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Test application only: no service of the test application uses the order-hold account owner
 * resolver, which would be removed from the container; the tests read it through this alias.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->alias('tmonier_company_accounts.test.account_owner_resolver', 'tmonier_order_hold.resolver.account_owner')
        ->public()
    ;
};
