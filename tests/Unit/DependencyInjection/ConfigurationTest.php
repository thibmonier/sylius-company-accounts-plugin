<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusCompanyAccountsPlugin\DependencyInjection\Configuration;
use Tmonier\SyliusCompanyAccountsPlugin\DependencyInjection\TmonierSyliusCompanyAccountsExtension;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\Company;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        /** @var array{company_orders_roles: list<string>, resources: array<string, array{classes: array{model: string}}>} $config */
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame(['admin', 'approver'], $config['company_orders_roles']);
        self::assertSame(Company::class, $config['resources']['company']['classes']['model']);
        self::assertArrayHasKey('company_member', $config['resources']);
        self::assertArrayHasKey('company_order', $config['resources']);
    }

    public function testUnknownRolesAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['company_orders_roles' => ['owner']]]);
    }

    public function testTheOrderHoldBridgeDecoratesTheAccountOwnerResolverWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new TmonierSyliusCompanyAccountsExtension())->load([['company_orders_roles' => ['admin', 'admin']]], $container);

        self::assertSame(['admin'], $container->getParameter('tmonier_company_accounts.company_orders_roles'));
        $definition = $container->getDefinition('tmonier_company_accounts.order_hold.account_owner_resolver');
        $decorated = $definition->getDecoratedService();
        self::assertNotNull($decorated);
        self::assertSame('tmonier_order_hold.resolver.account_owner', $decorated[0]);
        // Ignored when the order-hold bundle is installed but not enabled.
        self::assertSame(\Symfony\Component\DependencyInjection\ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $decorated[3] ?? null);
    }
}
