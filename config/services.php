<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCompanyAccountsPlugin\Api\Shop\CompanyMemberProvider;
use Tmonier\SyliusCompanyAccountsPlugin\Api\Shop\CompanyOrderProvider;
use Tmonier\SyliusCompanyAccountsPlugin\Api\Shop\CompanyProvider;
use Tmonier\SyliusCompanyAccountsPlugin\Api\Shop\ShopApiAccess;
use Tmonier\SyliusCompanyAccountsPlugin\Api\Shop\UpdateCompanyMemberProcessor;
use Tmonier\SyliusCompanyAccountsPlugin\Controller\Shop\CompanyAccountController;
use Tmonier\SyliusCompanyAccountsPlugin\Controller\Shop\CompanyAddressController;
use Tmonier\SyliusCompanyAccountsPlugin\EventListener\CompanyChangeListener;
use Tmonier\SyliusCompanyAccountsPlugin\EventListener\CompanyOrderListener;
use Tmonier\SyliusCompanyAccountsPlugin\EventListener\CompanyRemovalListener;
use Tmonier\SyliusCompanyAccountsPlugin\EventListener\CustomerRemovalListener;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactory;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Fixture\CompanyFixture;
use Tmonier\SyliusCompanyAccountsPlugin\Form\Type\CompanyAddressType;
use Tmonier\SyliusCompanyAccountsPlugin\Form\Type\CompanyMemberType;
use Tmonier\SyliusCompanyAccountsPlugin\Form\Type\CompanyType;
use Tmonier\SyliusCompanyAccountsPlugin\Form\Type\CustomerAutocompleteType;
use Tmonier\SyliusCompanyAccountsPlugin\Member\AccountHolderEligibilityChecker;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompanyOrderEligibilityCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CompositeCompanyOrderEligibilityChecker;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolver;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberUpdater;
use Tmonier\SyliusCompanyAccountsPlugin\Menu\AccountMenuListener;
use Tmonier\SyliusCompanyAccountsPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyVoter;
use Tmonier\SyliusCompanyAccountsPlugin\Security\OrderVoter;
use Tmonier\SyliusCompanyAccountsPlugin\Twig\CompanyAccountsExtension;
use Tmonier\SyliusCompanyAccountsPlugin\Twig\CompanyAddressesExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set('tmonier_company_accounts.current_member_resolver', CurrentMemberResolver::class)
        ->args([
            service('security.token_storage'),
            service('tmonier_company_accounts.repository.company_member'),
        ])
    ;
    $services->alias(CurrentMemberResolverInterface::class, 'tmonier_company_accounts.current_member_resolver');

    // Who an order is placed for: tagged checkers (autoconfigured in the application), any of
    // them accepting is enough.
    $services
        ->set('tmonier_company_accounts.company_order_eligibility_checker.account_holder', AccountHolderEligibilityChecker::class)
        ->tag('tmonier_company_accounts.company_order_eligibility_checker', ['priority' => 100])
    ;
    $services
        ->set('tmonier_company_accounts.company_order_eligibility_checker', CompositeCompanyOrderEligibilityChecker::class)
        ->args([tagged_iterator('tmonier_company_accounts.company_order_eligibility_checker')])
    ;
    $services->alias(CompanyOrderEligibilityCheckerInterface::class, 'tmonier_company_accounts.company_order_eligibility_checker');

    $services->set('tmonier_company_accounts.member_change_guard', MemberChangeGuard::class);
    $services->alias(MemberChangeGuard::class, 'tmonier_company_accounts.member_change_guard');
    $services
        ->set('tmonier_company_accounts.member_updater', MemberUpdater::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.member_change_guard'),
        ])
    ;
    $services->alias(MemberUpdater::class, 'tmonier_company_accounts.member_updater');

    // Voters
    $services
        ->set('tmonier_company_accounts.voter.company', CompanyVoter::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            param('tmonier_company_accounts.company_orders_roles'),
        ])
        ->tag('security.voter')
    ;
    $services
        ->set('tmonier_company_accounts.voter.order', OrderVoter::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_accounts.repository.company_order'),
            service('tmonier_company_accounts.voter.company'),
        ])
        ->tag('security.voter')
    ;

    // Doctrine
    $services
        ->set('tmonier_company_accounts.listener.company_change', CompanyChangeListener::class)
        ->args([service('event_dispatcher')])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush'])
        ->tag('doctrine.event_listener', ['event' => 'onClear'])
        ->tag('kernel.reset', ['method' => 'reset'])
    ;
    $services
        ->set('tmonier_company_accounts.listener.company_order', CompanyOrderListener::class)
        ->args([
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.repository.company_order'),
            service('tmonier_company_accounts.factory.company_order'),
            service('tmonier_company_accounts.company_order_eligibility_checker'),
        ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
    ;
    $services
        ->set('tmonier_company_accounts.listener.customer_removal', CustomerRemovalListener::class)
        ->args([service('tmonier_company_accounts.repository.company_member')])
        ->tag('doctrine.event_listener', ['event' => 'preRemove'])
    ;

    $services
        ->set('tmonier_company_accounts.listener.company_removal', CompanyRemovalListener::class)
        ->args([service('tmonier_company_accounts.repository.company_address')])
        ->tag('doctrine.event_listener', ['event' => 'preRemove'])
    ;

    // Factories and forms
    $services
        ->set('tmonier_company_accounts.custom_factory.company_member', CompanyMemberFactory::class)
        ->decorate('tmonier_company_accounts.factory.company_member')
        ->args([
            service('.inner'),
            service('tmonier_company_accounts.repository.company'),
        ])
        ->public()
    ;
    $services->alias(CompanyMemberFactoryInterface::class, 'tmonier_company_accounts.factory.company_member');

    $services
        ->set('tmonier_company_accounts.form.type.company', CompanyType::class)
        ->args([param('tmonier_company_accounts.model.company.class'), ['sylius']])
        ->tag('form.type')
    ;
    $services
        ->set('tmonier_company_accounts.form.type.company_member', CompanyMemberType::class)
        ->args([param('tmonier_company_accounts.model.company_member.class'), ['sylius']])
        ->tag('form.type')
    ;
    $services
        ->set('tmonier_company_accounts.form.type.company_address', CompanyAddressType::class)
        ->args([param('tmonier_company_accounts.model.company_address.class'), ['sylius']])
        ->tag('form.type')
    ;
    $services
        ->set('tmonier_company_accounts.form.type.customer_autocomplete', CustomerAutocompleteType::class)
        ->args([param('sylius.model.customer.class'), param('tmonier_company_accounts.model.company_member.class')])
        ->tag('form.type')
        ->tag('ux.entity_autocomplete_field')
    ;

    // Menus, Twig, shop pages
    $services
        ->set('tmonier_company_accounts.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;
    $services
        ->set('tmonier_company_accounts.listener.account_menu', AccountMenuListener::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('security.authorization_checker'),
        ])
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.shop.account'])
    ;
    $services
        ->set('tmonier_company_accounts.twig.extension', CompanyAccountsExtension::class)
        ->args([
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.current_member_resolver'),
        ])
        ->tag('twig.extension')
    ;
    $services
        ->set('tmonier_company_accounts.twig.addresses_extension', CompanyAddressesExtension::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_accounts.repository.company_address'),
            service('security.authorization_checker'),
        ])
        ->tag('twig.extension')
    ;
    $services
        ->set('tmonier_company_accounts.controller.shop.company_address', CompanyAddressController::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_accounts.repository.company_address'),
            service('tmonier_company_accounts.factory.company_address'),
            service('sylius.factory.address'),
            service('doctrine.orm.entity_manager'),
            service('sylius.context.cart'),
            service('sylius.order_processing.order_processor'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;
    $services
        ->set('tmonier_company_accounts.controller.shop.company_account', CompanyAccountController::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.repository.company_order'),
            service('tmonier_company_accounts.member_updater'),
            service('translator'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;

    // Shop API (read models) and admin API filter
    $services
        ->set(ShopApiAccess::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('security.authorization_checker'),
        ])
    ;
    $services
        ->set(CompanyProvider::class)
        ->args([service(ShopApiAccess::class), service('tmonier_company_accounts.repository.company_member')])
        ->tag('api_platform.state_provider')
    ;
    $services
        ->set(CompanyMemberProvider::class)
        ->args([service(ShopApiAccess::class), service('tmonier_company_accounts.repository.company_member')])
        ->tag('api_platform.state_provider')
    ;
    $services
        ->set(CompanyOrderProvider::class)
        ->args([
            service(ShopApiAccess::class),
            service('tmonier_company_accounts.repository.company_order'),
            service('api_platform.pagination'),
        ])
        ->tag('api_platform.state_provider')
    ;
    $services
        ->set(UpdateCompanyMemberProcessor::class)
        ->args([
            service(ShopApiAccess::class),
            service('tmonier_company_accounts.repository.company_member'),
            service('tmonier_company_accounts.member_updater'),
            service('translator'),
            service('sylius.context.locale'),
        ])
        ->tag('api_platform.state_processor')
    ;
    $services
        ->set('tmonier_company_accounts.api.company_member_company_filter')
        ->parent('api_platform.doctrine.orm.search_filter')
        ->args([['company.code' => 'exact']])
        ->tag('api_platform.filter')
    ;

    if (class_exists(\Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture::class)) {
        $services
            ->set('tmonier_company_accounts.fixture.company', CompanyFixture::class)
            ->args([
                service('doctrine.orm.entity_manager'),
                service('tmonier_company_accounts.factory.company'),
                service('tmonier_company_accounts.factory.company_member'),
                service('sylius.factory.customer'),
                service('sylius.factory.shop_user'),
                service('sylius.fixture.example_factory.order'),
            ])
            ->tag('sylius_fixtures.fixture')
        ;
    }
};
