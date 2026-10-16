# Sylius Company Accounts Plugin

[![Build](https://github.com/thibmonier/sylius-company-accounts-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-company-accounts-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**B2B company accounts for Sylius 2: several buyers of the same company, with roles, a company
area in the customer account, and the company as the account owner of their orders.**

Sylius 2 knows customers, not the companies they buy for. This plugin is an MIT alternative,
native to Sylius 2.2 / 2.3, to company accounts for merchants without Sylius Plus: companies
with members and roles, managed by the store in the back office and, for their colleagues, by
the company admins in the shop.

## Features

| Need | What the plugin does |
|---|---|
| Model the customer's company | **Company**: name, code, VAT number, registration number (SIREN, Companies House...), enabled flag, optional **default customer group** given to its members (prices, promotions). Own tables, no change to Customer, ShopUser or Order. |
| Several buyers per company | **Members**: existing customers attached to one company each, with a role (**admin**, **buyer**, **approver**) and an enabled flag. Approvers are stored for the purchase approval plugin (v1b). |
| Let the company manage its people | **My company** in the customer account: overview, **Members** (company admins change the role and the status of their colleagues; nobody changes themselves and the company keeps an enabled admin), **Company orders** (read-only). |
| Deliver to the company's sites | **Shared address book** (*My company > Addresses*): admins add, change and remove delivery addresses ("Lyon plant"); every member sees them and picks one at the checkout (*Deliver here*, copied to the order). Without shared address the checkout is unchanged. |
| Show company orders without breaking privacy | An order is a **company order** when an active member places it while signed in, or when staff create it for them with [tmonier/sylius-admin-order-creation-plugin](https://github.com/thibmonier/sylius-admin-order-creation-plugin) (recorded at checkout, kept if the member leaves; orders placed before joining stay personal). Admins and approvers see them (configurable); buyers see their own orders as before. The core order pages stay owner-only. |
| Minimise personal data | Members see the **names and roles** of their colleagues, never their e-mail or phone; company order pages show items, totals and states, not the addresses. |
| Manage companies in the back office | *Customers > Companies*: grid, form, **members sub-grid** with *Attach a customer* (autocomplete on customers with a shop account and no company), edit, *Detach*; the customer page shows the company and role. |
| Headless | Admin API `/api/v2/admin/companies`, `/api/v2/admin/company-members`; shop API `/api/v2/shop/company`, `/company/members` (`PATCH` for admins), `/company/orders`. Own resources, the core ones are not overridden. |
| Secure by construction | Exhaustive voters `COMPANY_VIEW`, `COMPANY_MANAGE_MEMBERS`, `COMPANY_MANAGE_ADDRESSES`, `ORDER_VIEW_OWN`, `ORDER_VIEW_COMPANY` on every page and API operation, tested against other companies, disabled members, disabled companies, non-members and visitors. |
| Share one account between colleagues | With [tmonier/sylius-order-hold-plugin](https://github.com/thibmonier/sylius-order-hold-plugin): the **company becomes the account owner** of its members' orders (e.g. one trade credit line per company with tmonier/sylius-trade-credit-plugin). Optional. |
| Feed other tools | **Events** after each change (`company.created`, `member.added`, `member.role_changed`...) for a webhook outbox, a timeline or an audit trail. |
| GDPR | Deleting a customer, or their shop account (the *Delete* action of the Sylius customer page), deletes their membership (event `member.removed`); the members' data stays in the core customer. |

Also included: translations in English, French, German, Spanish, Italian, Dutch and Polish; a
demo fixture; MySQL, MariaDB and PostgreSQL support (Schema API migration).

## Screenshots

![Company members in the customer account, with their roles](docs/images/shop-company-members.jpg)

![Company orders in the customer account](docs/images/shop-company-orders.jpg)

![Members of a company in the back office](docs/images/admin-company-members.jpg)

![Company form in the back office](docs/images/admin-company-edit.jpg)

![Member form in the back office](docs/images/admin-company-member-edit.jpg)

## Requirements

| | Version |
|---|---|
| PHP | 8.2+ (Sylius 2.2), 8.3+ (Sylius 2.3) |
| Sylius | 2.2.9+, 2.3 |
| Symfony | 6.4, 7.4 (Sylius 2.2); 6.4, 7.4, 8.0 (Sylius 2.3, Symfony 8 needs PHP 8.4) |
| Optional | tmonier/sylius-order-hold-plugin 1.0 (account owner bridge), tmonier/sylius-admin-order-creation-plugin 1.0 (staff orders count for the company) |

Sylius 2.2.9 is the floor shared with the order-hold kernel (GHSA-2rv4-pjmm-7fxf and
GHSA-vv4h-q2x8-74g4 are fixed from 2.2.9).

## Installation

1. Require the package:

    ```bash
    composer require tmonier/sylius-company-accounts-plugin
    ```

2. Register the bundle in `config/bundles.php` (Symfony Flex does it automatically once a recipe exists):

    ```php
    Tmonier\SyliusCompanyAccountsPlugin\TmonierSyliusCompanyAccountsPlugin::class => ['all' => true],
    ```

3. Import the configuration in `config/packages/tmonier_sylius_company_accounts.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusCompanyAccountsPlugin/config/config.yaml" }
    ```

4. Import the admin and shop routes in `config/routes/tmonier_sylius_company_accounts.yaml`:

    ```yaml
    tmonier_sylius_company_accounts:
        resource: "@TmonierSyliusCompanyAccountsPlugin/config/routes.yaml"
    ```

    The API operations are registered with the Sylius API (`/api/v2`) automatically. The shop
    pages live under `/{_locale}/account/company`, behind the Sylius account access control.

5. Run the migrations (three tables):

    ```bash
    bin/console doctrine:migrations:migrate
    ```

6. Optional: to make the company the account owner of the order-hold kernel, install
   `tmonier/sylius-order-hold-plugin` and enable its bundle. Nothing else to configure.
7. Optional: with `tmonier/sylius-admin-order-creation-plugin` enabled, orders that staff create
   for an active member are company orders. Nothing else to configure.

## Configuration

```yaml
# config/packages/tmonier_sylius_company_accounts.yaml
tmonier_sylius_company_accounts:
    # Member roles that see the orders of the whole company (ORDER_VIEW_COMPANY).
    company_orders_roles: [admin, approver]
```

The models (`resources.company`, `resources.company_member`, `resources.company_order`) can be
overridden like any Sylius resource.

### Roles

| Role | Shop |
|---|---|
| admin | company area, members (changes the role and status of colleagues), company orders |
| approver | company area, members (read-only), company orders |
| buyer | company area, members (read-only); their own orders in the core order history |

A disabled member, or every member of a disabled company, has no company area and is a regular
customer again. Store administrators manage everything in the back office (they are not bound
by the shop rules: a company may have no admin while it is being set up).

### Company orders

The company of an order is recorded in the flush that completes the checkout (shop, API,
admin tools or fixtures alike) when the customer is an enabled member of an enabled company and
the order is placed from their account. **Guest checkouts never count**: Sylius attaches an
existing customer to a guest order from the e-mail typed at checkout, so anybody knowing the
e-mail of a member could otherwise place a company order.

Orders created by staff with tmonier/sylius-admin-order-creation-plugin for an active member
count too (staff act on the member's behalf; recognised by that plugin's `AdminOrderCreation`
record). Other sources can be added with a service implementing
`Member\CompanyOrderEligibilityCheckerInterface` (autoconfigured; an order is eligible when one
checker accepts it).

### Default customer group

When a customer joins a company with a default group and has no group, they get it, and the
membership remembers that the company gave it. Only that group is taken back when they leave
(if still unchanged) or moved when the default group of the company changes; members with no
group get the new default. A group the merchant set, before joining or after, is never
touched. The group does not follow the enabled flags.

### Account owner (order-hold kernel)

When tmonier/sylius-order-hold-plugin is installed, `tmonier_order_hold.resolver.account_owner`
is decorated:

| Subject | Owner |
|---|---|
| A customer, active member | their company (`type: company`, identifier: company id) |
| A placed order recorded as a company order | that company, even if the member left since |
| A cart, or an order completing in the current flush (also from onFlush / postFlush listeners), of an active member, signed in or served by staff | their company |
| Anything else (guest orders, personal orders, disabled members or companies) | the decorated resolver (the customer) |

### Events

Symfony events, dispatched after the flush that made the change:

| Event | Class |
|---|---|
| `tmonier_company_accounts.company.created` / `.updated` (changed fields) / `.deleted` | `CompanyEvent` |
| `tmonier_company_accounts.member.added` / `.role_changed` / `.enabled_changed` / `.removed` | `CompanyMemberEvent` |

Ids and codes are captured before the flush (available for deleted rows); no personal data is
copied into the events. The events of a flush that fails are dropped, never dispatched by a later
flush. They are dispatched after the flush, not after an outer transaction opened by the caller
(`wrapInTransaction`, Messenger `doctrine_transaction`). To publish them with tmonier/sylius-webhook-outbox-plugin in the same
transaction as the change, implement its `Capture\ChangeDetectorInterface` on the company
entities (the outbox records during `onFlush`); a listener of these events runs after the
commit. tmonier/sylius-audit-log-plugin can audit the entities directly:

```yaml
tmonier_sylius_audit_log:
    resources:
        company:
            class: Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface
        company_member:
            class: Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface
```

## API

| Operation | Who | Notes |
|---|---|---|
| `GET /api/v2/shop/company` | signed-in member | code, name, VAT and registration numbers, own role, members count, permissions |
| `GET /api/v2/shop/company/members`, `GET .../members/{id}` | signed-in member | first name, last name, role, enabled, `me` |
| `PATCH /api/v2/shop/company/members/{id}` | company admin | `{"role": "approver", "enabled": false}` (`application/merge-patch+json`); 422 for self changes and the last admin, translated in the request locale (`Accept-Language`) |
| `GET /api/v2/shop/company/orders`, `GET .../orders/{number}` | admin, approver | number, date, placed by, states, totals, items |
| `/api/v2/admin/companies` (`{code}`) | administrators | CRUD |
| `/api/v2/admin/company-members` (`{id}`) | administrators | attach (`company`, `customer` IRIs, `role`), `PUT` role / enabled, detach; filter `?company.code=` |

Shop errors: 401 without token, 404 without membership or for an object of another company, 403
for a disabled membership or company, or a missing permission.

## Customisation

| Hook | Content |
|---|---|
| `tmonier_company_accounts_shop.account.company.{show,members,orders,order}.index.content.main` → `section` | Body of each company page |
| `tmonier_company_accounts_shop.account.company.*.index.content.main.header` → `title`, `navigation` | Company name, role, tabs |
| `tmonier_company_accounts_shop.account.company.{addresses,address_form}.index.content.main` → `section` | Address book list and add / edit form |
| `sylius_shop.checkout.address.content` → `tmonier_company_addresses` | Shared addresses offered above the address form of the checkout |
| `tmonier_company_accounts.shop.account.company.tabs` → `overview`, `members`, `orders`, `addresses` | One tab each (see [Extending](#extending)) |
| `sylius_shop.account.dashboard.index.content.main` → `tmonier_company` | Company card of the dashboard |
| `sylius_admin.customer.show.content.sections.general` → `tmonier_company` | Company row of the admin customer page |
| `tmonier_company_accounts_admin.company.update.content.header.title_block.actions` → `members` | Members button of the company form |
| `tmonier_company_accounts_admin.company_member.{create,update}.content.header` → `breadcrumbs`, `...header.title_block` → `title` | Company and customer of the member form |
| `tmonier_company_accounts_admin.company_member.update.content.form.sections.general` → `member` | Read-only customer and company of the edited member |

Services: `CurrentMemberResolverInterface`, `MemberChangeGuard` (rules) and `MemberUpdater`
(rules and write under a lock), `CompanyOrderEligibilityCheckerInterface` (tagged), the voters
(`tmonier_company_accounts.voter.company`, `.voter.order`) and the member factory
(`CompanyMemberFactoryInterface::createForCustomer()`).

## Extending

### Tabs of "My company"

The tab bar of the company pages is the Twig hook
`tmonier_company_accounts.shop.account.company.tabs` (1.1+). Each tab is a hookable rendering
one `<li class="nav-item">`; the built-in ones are `overview` (priority 300), `members` (200)
and `orders` (100, admins and approvers only). Add yours after them:

```yaml
sylius_twig_hooks:
    hooks:
        'tmonier_company_accounts.shop.account.company.tabs':
            app_budget:
                template: '@App/company/budget_tab.html.twig'
                priority: 0
```

```twig
{# hookable_metadata.context: company, member, active_tab #}
{% set context = hookable_metadata.context %}
{% if is_granted('COMPANY_VIEW', context.company) %}
    <li class="nav-item">
        <a class="nav-link {{ context.active_tab == 'budget' ? 'active' }}" href="{{ path('app_company_budget') }}"
           {% if context.active_tab == 'budget' %}aria-current="page"{% endif %}>Budget</a>
    </li>
{% endif %}
```

| Context | |
|---|---|
| `company` | `CompanyInterface` of the signed-in member |
| `member` | their `CompanyMemberInterface` |
| `active_tab` | `overview`, `members`, `orders` on this plugin's pages; the key your page passes |

To show the same tab bar on a page of your plugin (extending
`@SyliusShop/account/common/index.html.twig`), add the template
`@TmonierSyliusCompanyAccountsPlugin/shop/account/company/common/navigation.html.twig` to your
page header hook and pass `company`, `member` and `active_tab` (your tab key) in the page
context.

## Limitations

- One company per customer; no subsidiaries, invitations, per-company
  payment methods, spending limits or order approval in this version (approval is planned in
  tmonier/sylius-purchase-approval-plugin).
- A buyer pays and follows their own orders in the core order history; the company order pages
  are read-only and do not show addresses.
- Orders placed before installing the plugin are not company orders.
- Customers are attached by the store (back office or admin API); company admins cannot invite
  or attach customers in this version.
- The VAT number is not checked against VIES.
- The shared address book is delivery addresses only (no billing address, no default address, not exposed by the shop API in 1.2). The checkout copies the address: later changes never alter placed orders.
- Deleting a company deletes its memberships, its shared addresses and its company order links (the orders stay):
  disable it instead to keep the history.
- Plugins that anonymise customers instead of deleting them keep the membership (with the
  anonymised name): detach the member if needed.
- Changing a company's default group touches every member in the flush (fine for tens or
  hundreds of members, not for huge companies).

## Development

```bash
cp compose.override.dist.yml compose.override.yml   # publishes MySQL on 3306 and MailHog (or run `make init`)
docker compose up -d mysql mailhog   # empty root password
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n     # includes the demo company
```

Demo: `admin@acme-industries.example.com`, `buyer@...` and `approver@acme-industries.example.com`,
password `sylius`, then *My account > My company*.

The functional and integration tests need a **test database with the Sylius fixtures** (admin
`sylius`, a channel with hostname `localhost`). `tests/TestApplication/.env.test` uses the CI
credentials (`root:root`); with the local Docker MySQL, override it in
`tests/TestApplication/.env.test.local`:

```bash
echo 'DATABASE_URL=mysql://root@127.0.0.1:3306/tmonier_sylius_company_accounts_%kernel.environment%' > tests/TestApplication/.env.test.local
APP_ENV=test vendor/bin/console doctrine:database:create
APP_ENV=test vendor/bin/console doctrine:migrations:migrate -n
APP_ENV=test vendor/bin/console sylius:fixtures:load -n

vendor/bin/phpunit --testsuite unit
APP_ENV=test vendor/bin/phpunit --testsuite non-unit
vendor/bin/phpstan analyse
vendor/bin/ecs check
```

## License

MIT. See [LICENSE](LICENSE).
