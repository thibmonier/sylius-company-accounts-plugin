<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;

/** Same rules as the account pages: no membership 404, permission refused 403. */
final class ShopApiAccess
{
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** @return array{CompanyMemberInterface, CompanyInterface} */
    public function membership(string $attribute): array
    {
        $member = $this->currentMemberResolver->getMember();
        $company = $member?->getCompany();
        if (null === $member || null === $company) {
            throw new NotFoundHttpException('No company.');
        }
        if (!$this->authorizationChecker->isGranted($attribute, $company)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return [$member, $company];
    }

    public function isGranted(string $attribute, mixed $subject): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }
}
