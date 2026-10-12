<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Tmonier\SyliusOrderHoldPlugin\AccountOwner\AccountOwnerResolverInterface;

final class TmonierSyliusCompanyAccountsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{company_orders_roles: list<string>, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_company_accounts', $config['driver'], $config['resources'], $container);
        $container->setParameter('tmonier_company_accounts.company_orders_roles', array_values(array_unique($config['company_orders_roles'])));

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        // Optional bridge: the company becomes the account owner of the order-hold kernel
        // (shared trade credit...) only when tmonier/sylius-order-hold-plugin is installed.
        if (interface_exists(AccountOwnerResolverInterface::class)) {
            $loader->load('services/order_hold.php');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Grids are processed before this extension is loaded: the model class parameters have
        // to exist at prepend time.
        $configs = array_map(
            static fn (array $config): array => isset($config['resources']) ? ['resources' => $config['resources']] : [],
            $container->getExtensionConfig($this->getAlias()),
        );
        /** @var array{resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(sprintf('tmonier_company_accounts.model.%s.class', $name), $resource['classes']['model']);
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusCompanyAccountsPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusCompanyAccountsPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => ['paths' => [\dirname(__DIR__, 2) . '/config/api_platform', \dirname(__DIR__) . '/Api/Shop']],
            ]);
        }

        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_company_accounts';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusCompanyAccountsPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusCompanyAccountsPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
