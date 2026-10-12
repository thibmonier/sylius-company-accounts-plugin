# Changelog

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
