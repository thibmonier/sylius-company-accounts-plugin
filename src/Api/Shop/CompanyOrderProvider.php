<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** @implements ProviderInterface<CompanyOrderView> */
final class CompanyOrderProvider implements ProviderInterface
{
    public function __construct(
        private readonly ShopApiAccess $access,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly Pagination $pagination,
    ) {
    }

    /** @return CompanyOrderView|TraversablePaginator<CompanyOrderView> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CompanyOrderView|TraversablePaginator
    {
        [, $company] = $this->access->membership(CompanyPermissions::ORDER_VIEW_COMPANY);

        if ($operation instanceof CollectionOperationInterface) {
            $page = $this->pagination->getPage($context);
            $limit = $this->pagination->getLimit($operation, $context);
            $offset = $this->pagination->getOffset($operation, $context);
            $paginator = new Paginator($this->companyOrderRepository->createOrdersQueryBuilder($company)->setFirstResult($offset)->setMaxResults($limit));
            $views = [];
            foreach ($paginator as $order) {
                if ($order instanceof OrderInterface) {
                    $views[] = CompanyOrderView::fromOrder($order);
                }
            }

            return new TraversablePaginator(new \ArrayIterator($views), $page, $limit, count($paginator));
        }

        $number = $uriVariables['number'] ?? null;
        $order = is_string($number) ? $this->companyOrderRepository->findOrderByCompanyAndNumber($company, $number) : null;
        if (null === $order || !$this->access->isGranted(CompanyPermissions::ORDER_VIEW_COMPANY, $order)) {
            throw new NotFoundHttpException('Order not found.');
        }

        return CompanyOrderView::fromOrder($order);
    }
}
