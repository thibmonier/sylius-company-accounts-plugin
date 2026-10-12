# CLAUDE.md

Sylius 2 plugin: B2B company accounts. Companies with members (one company per customer, roles
admin / buyer / approver), a company area in the customer account (overview, members, company
orders), admin CRUD with a members sub-grid, shop and admin API, voters, events, and an optional
account owner bridge for tmonier/sylius-order-hold-plugin. Research and decisions: `SPEC.md`.
Public docs: `README.md`.

## Before publishing

- **Remove the path repository** (`repositories` in `composer.json`, `../sylius-order-hold-plugin`)
  once tmonier/sylius-order-hold-plugin 1.0 is on Packagist: it is a `require-dev` (bridge
  tests) and a `suggest`, never a `require`. CI cannot install it before that.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
APP_ENV=test vendor/bin/phpunit --testsuite non-unit   # functional + integration, test DB + fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3329, MailHog on
8048/1048), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local` (DATABASE_URL). Databases: `tmonier_sylius_company_accounts_dev` / `_test`,
both loaded with the default fixture suite, which includes the `tmonier_company_accounts` demo
fixture: company *Acme Industries* (`ACME_INDUSTRIES`, default group `wholesale`) with
`admin@`, `buyer@` and `approver@acme-industries.example.com` (password `sylius`, verified),
two orders each for the admin and the buyer. Dev server:
`symfony server:start -d --port=8095 --no-tls` (admin `sylius` / `sylius`); shop
`/en_US/account/company`.

`composer update` mirrors the whole order-hold directory (vendor included) into
`vendor/tmonier/sylius-order-hold-plugin`: delete its `vendor/` and `var/` afterwards (disk).

## Architecture

- Entities (`src/Entity`, XML mapped superclasses in `config/doctrine`, one migration in
  `src/Migrations`, Schema API): `Company` (code, name, VAT number, registration number,
  enabled, default customer group), `CompanyMember` (OneToOne unique customer, role, enabled;
  DB cascade on customer and company deletion), `CompanyOrder` (company of an order, recorded at
  checkout, OneToOne unique order).
- `Security/CompanyVoter` (COMPANY_VIEW, COMPANY_MANAGE_MEMBERS, ORDER_VIEW_COMPANY on a
  company) and `Security/OrderVoter` (ORDER_VIEW_OWN, ORDER_VIEW_COMPANY on an order), both
  through `Member/CurrentMemberResolver` (shop user token only). `company_orders_roles`
  (default admin + approver) drives ORDER_VIEW_COMPANY.
- `Member/MemberChangeGuard`: shop-side rules (active admin of the same company, valid role,
  no self change, keep one enabled admin). The store admin and the admin API are not bound.
- `EventListener/CompanyChangeListener` (onFlush/postFlush): `Event/CompanyEvents` from the
  unit of work, dispatched after the flush, plus the default customer group sync.
  `EventListener/CompanyOrderListener` (onFlush): `CompanyOrder` for orders whose checkout
  completes (insert or update) when placed by a signed-in active member
  (`Member/CompanyOrderEligibility`: never for guest orders). `CustomerRemovalListener`
  (preRemove): erasure of a customer removes the membership (event).
- Shop pages: `Controller/Shop/CompanyAccountController` (`config/routes/shop.yaml`, under
  `/account`, Sylius access control), templates extend `@SyliusShop/account/common/index.html.twig`
  with hook prefixes `tmonier_company_accounts_shop.account.company.{show,members,orders,order}`
  (`config/twig_hooks/shop.yaml`); dashboard card; `Menu/AccountMenuListener`.
  No membership: 404; refused: 403; object of another company: 404.
- Admin: resource routes `config/routes/admin.yaml` (company CRUD with hook prefix
  `tmonier_company_accounts_admin.company`; members nested under `/companies/{companyId}/members`
  like product variants), grids `config/grids`, `Form/Type` (customer autocomplete), customer
  page hook, `Menu/AdminMenuListener` (Customers section).
- API: admin resources on the entities (`config/api_platform`, groups in
  `config/serialization`); shop read models with attributes in `src/Api/Shop` (providers check
  the voters, `ShopApiAccess`), never e-mails or phone numbers of colleagues.
- Bridge: `Bridge/OrderHold/CompanyAccountOwnerResolver`, `config/services/order_hold.php`
  loaded only if the order-hold interface exists, decoration ignored if its bundle is disabled.
  The test application enables order-hold and aliases the resolver publicly
  (`tests/TestApplication/config/services.php`).

## Rules

- No core entity change: plugin tables only. The plugin writes `Customer::group` (default
  customer group of a company), nothing else of the core.
- Tests never depend on random fixtures: `tests/TestCatalog.php` creates companies, members,
  customers, a zone, shipping and payment methods, a product and orders (real checkout state
  machine) inside the rolled-back transaction; `tests/Functional/CompanyScenario.php` is the
  anti-IDOR scenario. No `markTestSkipped` (`NoSkippedTestsTest`).
- Labels in `messages`, validation and guard messages in `validators`, shop flash in `flashes`;
  every key in en, fr, de, es, it, nl, pl (`TranslationsTest` also checks keys used in
  src/templates/config). Clear the cache (dev and test) after adding a translation file.
- Positioning: MIT alternative to company accounts for merchants without Sylius Plus. Never
  claim that Plus or Elesto lacks a feature; do not reuse their role names or screens.
