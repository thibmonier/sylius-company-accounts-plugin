<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

/**
 * A company order, read-only, as seen by a member allowed to see the company orders
 * (ORDER_VIEW_COMPANY). Amounts in minor units of the order currency. Addresses and contact
 * details of the colleague are not exposed.
 */
#[ApiResource(
    shortName: 'ShopCompanyOrder',
    operations: [
        new GetCollection(
            uriTemplate: '/shop/company/orders',
            security: "is_granted('ROLE_USER')",
            name: 'tmonier_company_accounts_shop_company_orders_get',
            provider: CompanyOrderProvider::class,
        ),
        new Get(
            uriTemplate: '/shop/company/orders/{number}',
            security: "is_granted('ROLE_USER')",
            name: 'tmonier_company_accounts_shop_company_order_get',
            provider: CompanyOrderProvider::class,
        ),
    ],
)]
final readonly class CompanyOrderView
{
    /**
     * @param list<array{productName: string|null, variantName: string|null, variantCode: string|null, quantity: int, unitPrice: int, total: int}> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $number,
        public ?\DateTimeInterface $checkoutCompletedAt,
        public ?string $placedBy,
        public string $state,
        public ?string $paymentState,
        public ?string $shippingState,
        public ?string $currencyCode,
        public int $itemsTotal,
        public int $total,
        public array $items,
    ) {
    }

    public static function fromOrder(OrderInterface $order): self
    {
        $customer = $order->getCustomer();
        $items = [];
        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $items[] = [
                'productName' => $item->getProductName(),
                'variantName' => $item->getVariantName(),
                'variantCode' => $item->getVariant()?->getCode(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'total' => $item->getTotal(),
            ];
        }

        return new self(
            (string) $order->getNumber(),
            $order->getCheckoutCompletedAt(),
            $customer instanceof CustomerInterface ? trim($customer->getFullName()) : null,
            $order->getState(),
            $order->getPaymentState(),
            $order->getShippingState(),
            $order->getCurrencyCode(),
            $order->getItemsTotal(),
            $order->getTotal(),
            $items,
        );
    }
}
