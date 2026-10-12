<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;

/** Admin form of a member: the customer is chosen when attaching, then fixed. */
final class CompanyMemberType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', ChoiceType::class, [
                'label' => 'tmonier_company_accounts.form.role',
                'choices' => array_combine(
                    array_map(static fn (string $role): string => 'tmonier_company_accounts.role.' . $role, CompanyMemberInterface::ROLES),
                    CompanyMemberInterface::ROLES,
                ),
                'expanded' => true,
                'help' => 'tmonier_company_accounts.form.role_help',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
                $member = $event->getData();
                if ($member instanceof CompanyMemberInterface && null === $member->getId()) {
                    $event->getForm()->add('customer', CustomerAutocompleteType::class, [
                        'label' => 'sylius.ui.customer',
                        'help' => 'tmonier_company_accounts.form.customer_help',
                    ]);
                }
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_company_accounts_company_member';
    }
}
