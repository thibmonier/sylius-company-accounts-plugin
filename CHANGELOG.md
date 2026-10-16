# Changelog

## 1.2.0 (unreleased)

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
