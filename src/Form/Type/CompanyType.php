<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Form\Type;

use Sylius\Bundle\CustomerBundle\Form\Type\CustomerGroupChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class CompanyType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->add('name', TextType::class, [
                'label' => 'sylius.ui.name',
            ])
            ->add('vatNumber', TextType::class, [
                'label' => 'tmonier_company_accounts.form.vat_number',
                'required' => false,
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'tmonier_company_accounts.form.registration_number',
                'help' => 'tmonier_company_accounts.form.registration_number_help',
                'required' => false,
            ])
            ->add('defaultCustomerGroup', CustomerGroupChoiceType::class, [
                'label' => 'tmonier_company_accounts.form.default_customer_group',
                'help' => 'tmonier_company_accounts.form.default_customer_group_help',
                'required' => false,
                'placeholder' => 'tmonier_company_accounts.form.no_customer_group',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_company_accounts_company';
    }
}
