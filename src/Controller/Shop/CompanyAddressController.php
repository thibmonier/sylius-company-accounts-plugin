<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Form\Type\CompanyAddressType;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyAddressRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/**
 * Shared address book of "My company". Every member reads it and picks an address at the
 * checkout (COMPANY_VIEW); only the admins of the company write (COMPANY_MANAGE_ADDRESSES).
 * An address of another company is a 404, like the members and orders of another company.
 */
final class CompanyAddressController extends AbstractController
{
    private const TEMPLATE = '@TmonierSyliusCompanyAccountsPlugin/shop/account/company/';

    /** @param FactoryInterface<CompanyAddressInterface> $companyAddressFactory
     *  @param FactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private readonly CurrentMemberResolverInterface $currentMemberResolver,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly FactoryInterface $companyAddressFactory,
        private readonly FactoryInterface $addressFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly CartContextInterface $cartContext,
    ) {
    }

    public function indexAction(): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_VIEW);

        return $this->render(self::TEMPLATE . 'addresses.html.twig', [
            'company' => $company,
            'member' => $member,
            'addresses' => $this->addressRepository->findByCompany($company),
        ]);
    }

    public function createAction(Request $request): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_MANAGE_ADDRESSES);
        $entry = $this->companyAddressFactory->createNew();
        $entry->setCompany($company);
        $entry->setAddress($this->addressFactory->createNew());

        return $this->handleForm($request, $entry, $member, $company, 'tmonier_company_accounts.address_created');
    }

    public function updateAction(Request $request, int $id): Response
    {
        [$member, $company] = $this->membership(CompanyPermissions::COMPANY_MANAGE_ADDRESSES);

        return $this->handleForm($request, $this->entryOf($id, $company), $member, $company, 'tmonier_company_accounts.address_updated');
    }

    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        [, $company] = $this->membership(CompanyPermissions::COMPANY_MANAGE_ADDRESSES);
        $entry = $this->entryOf($id, $company);
        $this->denyUnlessValidToken($request, 'tmonier_company_address_' . $id);

        $this->entityManager->remove($entry);
        $this->entityManager->flush();
        $this->addFlash('success', 'tmonier_company_accounts.address_deleted');

        return $this->redirectToRoute('tmonier_company_accounts_shop_company_addresses');
    }

    /**
     * Copies a shared address to the shipping address of the current cart and goes back to the
     * address step, where it pre-fills the form. The order keeps its own copy.
     */
    public function useAction(Request $request, int $id): RedirectResponse
    {
        [, $company] = $this->membership(CompanyPermissions::COMPANY_VIEW);
        $source = $this->entryOf($id, $company)->getAddress();
        $this->denyUnlessValidToken($request, 'tmonier_company_address_use_' . $id);

        try {
            $order = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return $this->redirectToRoute('sylius_shop_cart_summary');
        }
        if (!$order instanceof OrderInterface || !$source instanceof AddressInterface) {
            return $this->redirectToRoute('sylius_shop_cart_summary');
        }

        $copy = $this->addressFactory->createNew();
        $copy->setFirstName($source->getFirstName());
        $copy->setLastName($source->getLastName());
        $copy->setPhoneNumber($source->getPhoneNumber());
        $copy->setCompany($source->getCompany());
        $copy->setCountryCode($source->getCountryCode());
        $copy->setProvinceCode($source->getProvinceCode());
        $copy->setProvinceName($source->getProvinceName());
        $copy->setStreet($source->getStreet());
        $copy->setCity($source->getCity());
        $copy->setPostcode($source->getPostcode());
        $order->setShippingAddress($copy);
        $this->entityManager->flush();

        return $this->redirectToRoute('sylius_shop_checkout_address');
    }

    private function handleForm(Request $request, CompanyAddressInterface $entry, CompanyMemberInterface $member, CompanyInterface $company, string $flash): Response
    {
        $form = $this->createForm(CompanyAddressType::class, $entry);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entry);
            $this->entityManager->flush();
            $this->addFlash('success', $flash);

            return $this->redirectToRoute('tmonier_company_accounts_shop_company_addresses');
        }

        return $this->render(self::TEMPLATE . 'address_form.html.twig', $this->formContext($form, $entry, $member, $company), new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** @return array<string, mixed> */
    private function formContext(FormInterface $form, CompanyAddressInterface $entry, CompanyMemberInterface $member, CompanyInterface $company): array
    {
        return ['company' => $company, 'member' => $member, 'form' => $form->createView(), 'entry' => $entry];
    }

    private function entryOf(int $id, CompanyInterface $company): CompanyAddressInterface
    {
        return $this->addressRepository->findOneByIdAndCompany($id, $company)
            ?? throw $this->createNotFoundException('Address not found.');
    }

    private function denyUnlessValidToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /** @return array{CompanyMemberInterface, CompanyInterface} */
    private function membership(string $attribute): array
    {
        $member = $this->currentMemberResolver->getMember();
        $company = $member?->getCompany();
        if (!$member instanceof CompanyMemberInterface || !$company instanceof CompanyInterface) {
            throw $this->createNotFoundException('No company.');
        }
        $this->denyAccessUnlessGranted($attribute, $company);

        return [$member, $company];
    }
}
