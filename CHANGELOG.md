# Changelog

## Unreleased (proposed version: 1.3.0)

### Added

- **Company addresses at the address step of the orders created by staff.** With
  `tmonier/sylius-admin-order-creation-plugin` 1.1 or later enabled, the shared address book of the
  company of the customer is offered first (group "Company addresses", above the customer's own
  address book) when an administrator creates an order for a member. Only the book of the company
  the customer is an active member of is offered (nothing for a customer without company, a
  disabled member or a disabled company), and it is the whitelist the submitted choice is checked
  against by the admin plugin. The order keeps a copy of the address.
  Service `tmonier_company_accounts.admin_order_address_source.company` (class
  `Bridge\AdminOrderCreation\CompanyAddressSource`), registered only when the address sources of
  the admin plugin exist.

### Compatibility

No BC break and no new hard dependency: `tmonier/sylius-admin-order-creation-plugin` stays a
`suggest` (a shop without it, or with its 1.0, is unchanged). The development constraint of the
admin plugin moves to `^1.1`.

## 1.2.0

### Added

- **Shared address book** of the company. Admins of the company add, change and remove
  delivery addresses (label and Sylius address) in *My company > Addresses*; every enabled member
  consults them there and picks one at the checkout (address step: *Deliver here* copies the
  address to the shipping address of the cart, recalculates the cart and sends the checkout back to
  the address step; the order keeps its own copy, reused on the next pick).
- New resource `company_address` (`CompanyAddressInterface`, `CompanyAddressRepositoryInterface`,
  table `tmonier_company_address`, additive migration `Version20261016100000`).
- New permission `CompanyPermissions::COMPANY_MANAGE_ADDRESSES` (enabled admin of the company);
  reading the book is `COMPANY_VIEW`.
- Hook `sylius_shop.checkout.address.content` > `tmonier_company_addresses`, tab hookable
  `tmonier_company_accounts.shop.account.company.tabs` > `addresses`, Twig function
  `tmonier_company_addresses()`.
- Translations in the seven languages.

### Compatibility

No BC break: only additions. No existing table, column, service, route, interface or method
signature changes (`CompanyInterface` is untouched: the link is one-way, from the address to the
company). A company without shared address, a customer without company and a visitor get the
checkout of 1.1. The only visible change for existing members is the new *Addresses* tab of *My
company*. Run the Doctrine migrations (a new table, `down()` drops it).

`CompanyPermissions::ALL` now contains a 5th permission (`COMPANY_MANAGE_ADDRESSES`): review any
application code that grants or iterates `ALL`.

## 1.1.0

- Extension point: the "My company" tab bar is the Twig hook
  `tmonier_company_accounts.shop.account.company.tabs` (context `company`, `member`,
  `active_tab`); other plugins add tabs and can render the tab bar on their own pages. Same
  markup as 1.0 (the active tab now also has `aria-current="page"`).
- Admin member form: the required marker is on the role field only, not on each role.

## 1.0.0

First release: companies with members and roles, company area in the customer account, admin
CRUD, shop and admin API, voters, events, account owner bridge for the order-hold kernel, staff
orders of admin-order-creation.
