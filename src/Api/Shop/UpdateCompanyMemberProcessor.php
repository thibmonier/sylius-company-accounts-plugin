<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Api\Shop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberUpdater;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyMemberRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** @implements ProcessorInterface<UpdateCompanyMemberInput, CompanyMemberView> */
final class UpdateCompanyMemberProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShopApiAccess $access,
        private readonly CompanyMemberRepositoryInterface $memberRepository,
        private readonly MemberUpdater $memberUpdater,
        private readonly TranslatorInterface $translator,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /** The locale of the request (Accept-Language, channel): the API answers in it. */
    private function locale(): ?string
    {
        try {
            return $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            return null;
        }
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

        $role = $data->role ?? $target->getRole();
        $enabled = $data->enabled ?? $target->isEnabled();
        $error = $this->memberUpdater->update($actor, $target, $role, $enabled);
        if (null !== $error) {
            throw new UnprocessableEntityHttpException($this->translator->trans($error, [], 'validators', $this->locale()));
        }

        return CompanyMemberView::fromMember($target, $actor);
    }
}
