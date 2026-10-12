<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** @implements ProcessorInterface<UpdateCompanyMemberInput, CompanyMemberView> */
final class UpdateCompanyMemberProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShopApiAccess $access,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly MemberChangeGuard $memberChangeGuard,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CompanyMemberView
    {
        [$actor, $company] = $this->access->membership(CompanyPermissions::COMPANY_VIEW);
        $id = $uriVariables['id'] ?? null;
        $target = is_numeric($id) ? $this->memberRepository->findOneByIdAndCompany((int) $id, $company) : null;
        if (null === $target) {
            throw new NotFoundHttpException('Member not found.');
        }
        if (!$this->access->isGranted(CompanyPermissions::COMPANY_MANAGE_MEMBERS, $company)) {
            throw new AccessDeniedHttpException('Only company admins manage members.');
        }

        $role = $data instanceof UpdateCompanyMemberInput && null !== $data->role ? $data->role : $target->getRole();
        $enabled = $data instanceof UpdateCompanyMemberInput && null !== $data->enabled ? $data->enabled : $target->isEnabled();
        $error = $this->memberChangeGuard->check($actor, $target, $role, $enabled);
        if (null !== $error) {
            throw new UnprocessableEntityHttpException($this->translator->trans($error, [], 'validators', 'en'));
        }

        $target->setRole($role);
        $target->setEnabled($enabled);
        $this->entityManager->flush();

        return CompanyMemberView::fromMember($target, $actor);
    }
}
