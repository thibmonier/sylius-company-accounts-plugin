<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Form\Type;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField(
    alias: 'tmonier_company_accounts_customer',
    route: 'sylius_admin_entity_autocomplete',
)]
final class CustomerAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly string $customerClass,
        private readonly string $memberClass,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->customerClass,
            // Customers who can use a company area (a shop account) and are in no company yet.
            'query_builder' => fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('customer')
                ->innerJoin('customer.user', 'user')
                ->leftJoin($this->memberClass, 'membership', Join::WITH, 'membership.customer = customer')
                ->andWhere('membership.id IS NULL')
                ->orderBy('customer.email', 'ASC'),
            'searchable_fields' => ['email', 'firstName', 'lastName'],
            'choice_label' => static fn (CustomerInterface $customer): string => self::label($customer),
            'security' => 'ROLE_ADMINISTRATION_ACCESS',
            'max_results' => 20,
        ]);
    }

    public static function label(CustomerInterface $customer): string
    {
        $name = trim($customer->getFullName());

        return '' === $name ? (string) $customer->getEmail() : sprintf('%s (%s)', $name, (string) $customer->getEmail());
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_company_accounts_customer_autocomplete';
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
