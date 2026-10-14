<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** "My company" in the customer account menu, for enabled members of an enabled company only. */
final readonly class AccountMenuListener
{
    public function __construct(
        private CurrentMemberResolverInterface $currentMemberResolver,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $company = $this->currentMemberResolver->getMember()?->getCompany();
        if (!$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || !$this->authorizationChecker->isGranted(CompanyPermissions::COMPANY_VIEW, $company)) {
            return;
        }

        $event->getMenu()
            ->addChild('tmonier_company', ['route' => 'tmonier_company_accounts_shop_company_show'])
            ->setLabel('tmonier_company_accounts.ui.my_company')
            ->setLabelAttribute('icon', 'tabler:building')
        ;
    }
}
