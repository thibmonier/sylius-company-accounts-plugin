<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\Company;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMember;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrder;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepository;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepository;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyRepository;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_company_accounts');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('company_orders_roles')
                    ->info('Member roles that see the orders of the whole company (ORDER_VIEW_COMPANY). Other members only see their own orders.')
                    ->defaultValue([CompanyMemberInterface::ROLE_ADMIN, CompanyMemberInterface::ROLE_APPROVER])
                    ->enumPrototype()->values(CompanyMemberInterface::ROLES)->end()
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->append($this->resource('company', Company::class, CompanyInterface::class, CompanyRepository::class))
                    ->append($this->resource('company_member', CompanyMember::class, CompanyMemberInterface::class, CompanyMemberRepository::class))
                    ->append($this->resource('company_order', CompanyOrder::class, CompanyOrderInterface::class, CompanyOrderRepository::class))
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function resource(string $name, string $model, string $interface, string $repository): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue($repository)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
