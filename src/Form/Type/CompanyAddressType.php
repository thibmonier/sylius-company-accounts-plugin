<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Form\Type;

use Sylius\Bundle\AddressingBundle\Form\Type\AddressType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class CompanyAddressType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'tmonier_company_accounts.form.address_label',
                'help' => 'tmonier_company_accounts.form.address_label_help',
            ])
            ->add('address', AddressType::class, [
                'label' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_company_accounts_company_address';
    }
}
