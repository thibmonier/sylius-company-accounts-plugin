<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** @implements ProviderInterface<CompanyMemberView> */
final readonly class CompanyMemberProvider implements ProviderInterface
{
    public function __construct(
        private ShopApiAccess $access,
        private CompanyMemberRepositoryInterface $memberRepository,
    ) {
    }

    /** @return CompanyMemberView|list<CompanyMemberView> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CompanyMemberView|array
    {
        [$viewer, $company] = $this->access->membership(CompanyPermissions::COMPANY_VIEW);

        if ($operation instanceof CollectionOperationInterface) {
            return array_map(
                static fn (\Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface $member): CompanyMemberView => CompanyMemberView::fromMember($member, $viewer),
                $this->memberRepository->findByCompany($company),
            );
        }

        $id = $uriVariables['id'] ?? null;
        $member = is_numeric($id) ? $this->memberRepository->findOneByIdAndCompany((int) $id, $company) : null;
        if (!$member instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface) {
            throw new NotFoundHttpException('Member not found.');
        }

        return CompanyMemberView::fromMember($member, $viewer);
    }
}
