<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Controller\Shop;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberUpdater;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/**
 * "My company" pages of the customer account (behind the Sylius `/account` access control).
 * No membership: 404; disabled membership or company, or missing permission: 403; an order or a
 * member of another company: 404.
 */
final class CompanyAccountController extends AbstractController
{
    public const ORDERS_PER_PAGE = 20;

    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly CompanyOrderRepositoryInterface $companyOrderRepository,
        private readonly MemberUpdater $memberUpdater,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function showAction(): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_VIEW);

        return $this->render('@TmonierSyliusCompanyAccountsPlugin/shop/account/company/show.html.twig', [
            'company' => $company,
            'member' => $member,
            'members_count' => count($this->memberRepository->findByCompany($company)),
        ]);
    }

    public function membersAction(): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_VIEW);

        return $this->render('@TmonierSyliusCompanyAccountsPlugin/shop/account/company/members.html.twig', [
            'company' => $company,
            'member' => $member,
            'members' => $this->memberRepository->findByCompany($company),
            'roles' => CompanyMemberInterface::ROLES,
        ]);
    }

    public function updateMemberAction(Request $request, int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_MANAGE_MEMBERS);
        $target = $this->memberRepository->findOneByIdAndCompany($id, $company);
        if (!$target instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface) {
            throw $this->createNotFoundException('Member not found.');
        }
        if (!$this->isCsrfTokenValid('tmonier_company_member_' . $id, (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $role = (string) $request->request->get('role', $target->getRole());
        $enabled = $request->request->getBoolean('enabled');
        $error = $this->memberUpdater->update($member, $target, $role, $enabled);
        if (MemberUpdater::ERROR_TARGET_NOT_FOUND === $error) {
            throw $this->createNotFoundException('Member not found.');
        }
        if (MemberChangeGuard::ERROR_NOT_ALLOWED === $error) {
            throw $this->createAccessDeniedException();
        }
        if (null !== $error) {
            $this->addFlash('error', $this->translator->trans($error, [], 'validators'));

            return $this->redirectToRoute('tmonier_company_accounts_shop_company_members');
        }

        $this->addFlash('success', 'tmonier_company_accounts.member_updated');

        return $this->redirectToRoute('tmonier_company_accounts_shop_company_members');
    }

    public function ordersAction(Request $request): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::ORDER_VIEW_COMPANY);

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = new Paginator(
            $this->companyOrderRepository->createOrdersQueryBuilder($company)
                ->setFirstResult(($page - 1) * self::ORDERS_PER_PAGE)
                ->setMaxResults(self::ORDERS_PER_PAGE),
        );
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / self::ORDERS_PER_PAGE));
        // Old bookmark or fewer orders since: the last page, not an empty list.
        if ($page > $pages) {
            return $this->redirectToRoute('tmonier_company_accounts_shop_company_orders', ['page' => $pages]);
        }

        return $this->render('@TmonierSyliusCompanyAccountsPlugin/shop/account/company/orders.html.twig', [
            'company' => $company,
            'member' => $member,
            'orders' => iterator_to_array($paginator),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function showOrderAction(string $number): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_VIEW);
        $order = $this->companyOrderRepository->findOrderByCompanyAndNumber($company, $number);
        if (!$order instanceof OrderInterface) {
            throw $this->createNotFoundException('Order not found.');
        }
        $this->denyAccessUnlessGranted(CompanyPermissions::ORDER_VIEW_COMPANY, $order);

        return $this->render('@TmonierSyliusCompanyAccountsPlugin/shop/account/company/order.html.twig', [
            'company' => $company,
            'member' => $member,
            'order' => $order,
        ]);
    }

    /** @return array{CompanyMemberInterface, CompanyInterface} */
    private function membership(string $attribute): array
    {
        $member = $this->currentMemberResolver->getMember();
        $company = $member?->getCompany();
        if (!$member instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface || !$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface) {
            throw $this->createNotFoundException('No company.');
        }
        $this->denyAccessUnlessGranted($attribute, $company);

        return [$member, $company];
    }
}
