# Company accounts plugin: specification (v1a)

Status: v1a built on 2026-10-12. Source: B2B decision memo of 2026-10-11 (4 research reports,
8 adversarial verifications), section 3.2 "Comptes entreprise" (v1a only), section 2
(dependencies and shared order-hold kernel) and section 5 (verifier corrections). Where a
verifier corrected the research, the verified version is kept.

## 1. Research conclusions

- **Gap in open source**: Packagist and GitHub return no maintained company / organisation
  plugin for Sylius 2 (memo, table 1). A BitBag agency module exists (licence not verified).
- **Proprietary overlap is high**: the Sylius Plus B2B Suite (proprietary, quote-based price,
  v4.0.0-ALPHA.6 of 2026-08-18, https://addons.sylius.com/en_US/products/b2b-suite) and the
  Elesto distribution (https://addons.sylius.com/en_US/products/elesto-distribution) cover
  organisations and roles; BitBag (https://bitbag.io/blog/b2b-ecommerce-challenges-sylius-b2b-suite,
  2025-12-04) attributes roles, per-user or per-role limits, approval above a threshold and
  finance profiles to the B2B Suite. Positioning therefore: **"MIT alternative, native Sylius
  2.3, to company accounts for merchants without Sylius Plus"**. The documentation never states
  that Plus or Elesto lacks a feature (comparative advertising, Directive 2006/114/EC), the
  entity is named `Company` (not Organization), and no Plus role name or screen is reused.
- **GDPR**: visibility between colleagues rests on art. 6(1)(b)/(f) (contract with the
  company, legitimate interest), not on the employment contract. Hence data minimisation:
  colleagues see names and roles, never e-mails or phone numbers; company order pages show no
  address. Erasure of a customer removes their membership. Not legal advice.
- **Split v1a / v1b** (memo): v1a = companies, members, roles, voters, account pages, admin,
  API, owner resolver, events. v1b (tmonier/sylius-purchase-approval-plugin) = order approval
  with limits, which depends on v1a and the order-hold kernel; the `approver` role is only
  stored here.
- **Shared kernel** (memo section 2): trade credit must work without companies (owner =
  customer) and plug into them through `AccountOwnerResolverInterface` of
  tmonier/sylius-order-hold-plugin. This plugin implements that decoration.

## 2. Scope v1a

In: `Company`, `CompanyMember` (one company per customer, roles admin / buyer / approver,
enabled), voters `COMPANY_VIEW`, `COMPANY_MANAGE_MEMBERS`, `ORDER_VIEW_OWN`,
`ORDER_VIEW_COMPANY` with anti-IDOR tests on every shop route and API operation; account pages
"My company", "Members", "Company orders" + a read-only company order page; admin CRUD,
members sub-grid, attach (autocomplete) / edit / detach, company row on the customer page;
own shop and admin API resources; order-hold owner bridge; events; default customer group;
GDPR cascade; 7 languages; demo fixture.

Out (v1b and later): order approval, limits and budgets, invitations, subsidiaries, payment
restrictions per company, shared address book, several companies per customer, accountant role.

## 3. Design

### 3.1 Model

- `tmonier_company`: code (unique), name, vat_number, registration_number (trimmed, empty =
  null), enabled, default_customer_group_id (SET NULL on group deletion), timestamps.
- `tmonier_company_member`: company_id (CASCADE), customer_id (**unique**, CASCADE), role,
  enabled, timestamps. Unique customer = one company per customer at the database level, also
  validated (`UniqueEntity`).
- `tmonier_company_order`: company_id (CASCADE), order_id (unique, CASCADE), created_at. Why a
  link table and not "orders of the current members": with a membership-based query, a buyer's
  personal orders placed before joining would become visible to the new company, and a
  member's company orders would follow them to another company. The link is written once, in
  the flush that completes the checkout.
- Migration with the Schema API (MySQL, MariaDB, PostgreSQL), index and FK names identical to
  the Doctrine mapping (`doctrine:schema:update --dump-sql` shows no plugin change).

### 3.2 Permissions

- `CurrentMemberResolver`: membership of the **shop user** of the token only (administrators,
  API clients and visitors have none).
- `CompanyVoter` (subject company): `COMPANY_VIEW` = enabled member of the enabled company;
  `COMPANY_MANAGE_MEMBERS` = + admin role; `ORDER_VIEW_COMPANY` = + role in
  `company_orders_roles` (default admin, approver).
- `OrderVoter` (subject order): `ORDER_VIEW_OWN` = order of the signed-in customer;
  `ORDER_VIEW_COMPANY` = the order is linked to a company where the user has
  `ORDER_VIEW_COMPANY`.
- Responses (shop pages and API): no membership 404, refused permission 403, an order or a
  member of another company 404 (no existence leak across companies), visitor 302 to login /
  401.
- Core order pages are untouched (owner-only, tested).

### 3.3 Member changes in the shop

`MemberChangeGuard`: the actor is an active admin of the same company, the role exists, no
change of one's own role or status (no self lock-out), and the last enabled admin cannot be
demoted or disabled. Store administrators are not bound: a company can be created before its
first admin is attached.

Concurrency: `MemberUpdater` runs the guard and the write in one transaction that first locks
the company row (`PESSIMISTIC_WRITE`), re-reads the actor and the target and counts the enabled
admins in the database. Two admins demoting each other at the same time are serialised: the
second request waits, then sees that its actor is no longer an admin and is refused. A target
deleted or moved meanwhile answers 404, an actor deleted meanwhile 403 (checked in the database
under the lock, the entity manager stays open). Shop page
and shop API share it (`tests/TestApplication/config/dbal_savepoints.php` for DBAL 3).

### 3.4 Company orders

`CompanyOrderListener` (onFlush): for orders inserted or updated with `checkoutState` becoming
`completed`, when the order is eligible and the customer is an active member: persist a
`CompanyOrder` in the same flush. It does not depend on a controller or a state machine
adapter (shop, API, fixtures).

Eligibility is an extension point: `CompanyOrderEligibilityCheckerInterface`, tagged
`tmonier_company_accounts.company_order_eligibility_checker` (autoconfigured), an order is
eligible when one checker accepts it. Shipped checkers:

- `AccountHolderEligibilityChecker`: placed by the account holder (`createdByGuest` false and a
  shop user). Guest orders never count: Sylius attaches an existing customer to a guest
  checkout from the typed e-mail (the same issue was found in tier pricing).
- `Bridge/AdminOrderCreation/StaffOrderEligibilityChecker`, registered only when the
  tmonier/sylius-admin-order-creation-plugin bundle is enabled. Product decision: an order that
  staff create for an active member of an active company is a company order (staff act on the
  member's behalf). That plugin sets the customer without authorization (`createdByGuest`
  stays true), so its marker is used: an `AdminOrderCreation` row for the order (in the
  database, or scheduled for insertion in the running flush). That plugin is not modified.

### 3.5 Account owner bridge (order-hold kernel)

Decision: **optional dependency** (`suggest` + `require-dev`), not `require`. The decorator
`config/services/order_hold.php` is loaded only when `AccountOwnerResolverInterface` exists and
uses `IGNORE_ON_INVALID_REFERENCE` (package installed, bundle disabled). Rules (table in the
README): link recorded (in the database, or a `CompanyOrder` scheduled for insertion in the
running flush) → its company; placed order without link → decorated (customer); cart or order
completing in the current flush, when eligible (3.4), of an active member → company. Type
`company`, identifier = company id.

Inside a flush (onFlush, postFlush listeners) the unit of work has already copied the new
values into the original data, so "completing now" is read from the change set
(`getEntityChangeSet()['checkoutState']`) and from the scheduled insertions, never from the
original data alone. Tested from onFlush and postFlush listeners, and for guest carts and guest
checkouts in progress (always the customer).

### 3.6 Default customer group

Applied in `CompanyChangeListener` (onFlush, `recomputeSingleEntityChangeSet` on the customer):
given on joining if the customer has no group, and then remembered on the membership
(`CompanyMember::customerGroupAssigned`; the migration that adds it marks existing members
whose group is the default group of their company). Only a group the company gave is taken back on
leaving (if still unchanged) or moved when the company default changes; members with no group
receive the new default. A group the merchant set, before joining or after, is never touched.
It is the only core field the plugin writes.

### 3.7 Events

`CompanyChangeListener` derives `company.created/updated/deleted` and
`member.added/role_changed/enabled_changed/removed` from the unit of work (any source),
captures ids and codes before the flush and dispatches after it. No personal data in the event
objects. Events are collected per flush and queued by the postFlush of that flush: a flush
that fails (including one made, and caught, by an event subscriber during the dispatch) never
reaches postFlush, so its events are never dispatched. Limitation:
events are dispatched after the flush, not after an outer transaction opened by the caller
(`wrapInTransaction`, Messenger `doctrine_transaction` middleware); if that outer transaction
rolls back, the events have already been dispatched. A transactional outbox needs a `ChangeDetectorInterface` of the outbox plugin instead
(documented, not shipped: no dependency on the outbox).

### 3.8 UI and API

- Shop: own controller, templates extending `@SyliusShop/account/common/index.html.twig` with
  hook prefixes (fallback on the Sylius account hooks: breadcrumbs, menu, header), dashboard
  card, account menu item for active members. Member edit = one small form per row with CSRF.
- Admin: Sylius resource routes; members nested like product variants
  (`/companies/{companyId}/members`, repository `findOneByIdAndCompanyId` for edit and
  detach), UX autocomplete on customers with a shop account and no company yet (e-mail, first
and last name), customer page row. Member forms name the company and the customer (title,
breadcrumbs, read-only customer on edit).
- API: admin resources on the entities (identifier `code` for companies), shop read models
  (`ShopCompany`, `ShopCompanyMember`, `ShopCompanyOrder`) whose providers apply the voters.
  Refusals (422) are translated in the request locale (`Accept-Language`, channel locales).

### 3.9 Erasure (GDPR)

The membership is removed (event `member.removed`) when the customer is deleted and when the
shop user is deleted: Sylius 2 has no customer deletion in the back office, its "Delete" action
on the customer page deletes the shop account. A former member without an account is then no
longer listed to colleagues nor counted as an admin. Plugins that anonymise customers instead
of deleting them keep the membership (name anonymised by them); detach the member if needed.

## 4. Decisions and open points

Conservative choices made where the brief and the memo were silent:

1. **Company orders are visible to admins and approvers only** (configurable with
   `company_orders_roles`): buyers seeing every colleague's orders is not needed for v1a and
   exposes more personal data.
2. **Company order = link recorded at checkout**, not "orders of the current members" (3.1).
   Orders placed before installing the plugin are not company orders.
3. **Guest checkouts never become company orders** and never resolve to the company owner.
   **Staff orders** (admin order creation plugin) for an active member do (3.4).
4. **No self change at all** for a company admin in the shop (role and status), plus the
   last-admin rule.
5. **Order-hold is optional** (3.5).
6. **Company order pages hide addresses** and contact data; the buyer keeps the core pages
   (payment, tracking).
7. **The default group does not follow the enabled flags** (only the membership), and only a
   group the company gave is ever taken back or moved.
8. The store back office and admin API are not bound by the shop member rules.
9. Sylius floor `^2.2.9 || ^2.3`, like the order-hold kernel (the plugin itself does not touch
   payment or cart).

Not verified:

- Account and customer page hooks on Sylius 2.2: the hook names used here
  (`sylius_shop.account.common.index.*`, `sylius_shop.account.dashboard.index.content.main`,
  `sylius_admin.customer.show.content.*`) exist in the 2.2 branch on GitHub, but the plugin was
  only run locally on 2.3.0 (the CI matrix runs 2.2 jobs).
- Behaviour with the winzou state machine adapter: the company order link does not depend on
  it (Doctrine listener), but the order-hold kernel refuses winzou for its graphs.
- Real B2B Suite / Elesto feature depth (documentation behind authentication).
