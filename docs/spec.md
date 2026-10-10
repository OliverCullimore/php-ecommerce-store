# Filament & Print Shop Manager — Definitive Spec

## 1. Purpose

A PHP web app to run the operational side of a small manufacturing/selling
business — stock, production, purchasing, sales orders, payments,
invoicing — plus a public storefront, built as a learning vehicle for
modern PHP practices (Slim 4, PSR standards, automated testing,
Composer-based dependency management).

The domain model is a single, generic items/variants/BOM structure, not
tied to any one product line. Filament is one variant among many; a raw
table leg, a painted table leg sub-assembly, and a finished dining table
all live in the same tables, distinguished only by data.

## 2. Domain Model: Items, Variants & BOM

- **`items`** — a display-level grouping: "Oak Dining Table", "eSun PLA+",
  "Table Leg". Name/description only, nothing behavioural.
- **`item_variants`** — the actual tracked/priced/consumed/sold unit: SKU,
  unit, stock, cost, optionally a sale price. Everything happens here.
- **`variant_bom_lines`** — a variant's recipe: either a *component* line
  (self-referencing another variant + quantity) or a *resource* line (a
  capital asset or labour + hours), distinguished by `line_type` — the
  same two-nullable-FK shape `purchase_order_items` uses for "what kind of
  thing is this." A variant with any component lines is **manufactured**
  (built via production, deducts its own components); with none, it's
  **purchased** (restocked only via PO). This flag-by-absence is what
  makes multi-level manufacturing work without extra tables — a component
  can itself be manufactured from smaller components.
- **`resources`** is a separate concept from items — capacity/depreciation
  (capital assets, labour), not stock that depletes to zero.
- **`sellable`** lives on the variant, not the item — whether it appears
  on the storefront and can be sold directly.

### Worked example: three BOM levels, one product line

```
Table Leg (item)
  └─ variant "Standard" — purchased, no BOM

Painted Table Leg (item)                          <- sub-assembly, NOT sellable
  └─ variant "Light Oak Finish" — manufactured, BOM:
       1× Table Leg[Standard] + a little Wood Stain[Light] + 0.1 hr Labour

Oak Dining Table (item)                            <- sellable, colour variants
  ├─ variant "Light Oak" — sellable, BOM:
  │    4× Painted Table Leg[Light Oak Finish] + 1× Oak Tabletop[Standard]
  │    + 16× M6 Screw + 2 hrs Assembly Labour
  └─ variant "Dark Oak" — same shape, dark-finish components
```

```
eSun PLA+ (item)
  └─ variant "Silk Red 1.75mm" — purchased, no BOM

Phone Stand (item)                                  <- sellable, colour variants
  └─ variant "Red" — sellable, BOM:
       45g eSun PLA+[Silk Red 1.75mm] + 5ml IPA + 0.003 Print Sheets
       + 2.5 hrs Printer #1 + 0.1 hrs Setup Labour
```

Cost of "Light Oak" table = its own resource cost + the recursive cost of
Painted Table Leg[Light Oak Finish] × 4, which is itself resource cost +
Table Leg[Standard]'s cost + Wood Stain's cost. This roll-up is computed
in the domain layer (recursive PHP over the loaded BOM tree), not a
recursive SQL query — framework/DB-agnostic and easy to unit test.

A variant's BOM must never reference itself, directly or transitively.
Enforced at the application layer: before saving a component line, walk
the proposed component's own BOM tree and reject if the parent variant
appears anywhere in it.

Full cascading requirements explosion (auto-computing "you need 12 more
painted legs because you're producing 5 more tables") is a natural
extension of this model but is not built in v1. Production actions
consume one BOM level and hard-fail with a clear shortage message if a
component is insufficient; the admin produces the sub-assembly first. The
manufacturing screen (§10) surfaces shortages at every level independently.

## 3. Catalogue: Categories & Attributes

- **`categories`** — self-referencing (`parent_category_id`), unlimited
  depth. `sort_order` is scoped per parent, set via admin drag-and-drop,
  and that same ordered tree renders the storefront navigation. Same
  cycle-check discipline as the BOM applies: a category must never become
  its own ancestor.
- **`item_category_assignments`** — many-to-many, at the **item** level,
  not the variant level. A category listing page shows one tile per
  product, not one per colour — clicking through is where variants and
  their attribute-driven option pickers come in. Browsing a category
  includes items assigned to any of its descendant categories.
- **`attributes`** — e.g. "Colour", "Diameter". `display_on_storefront`
  toggles whether it appears as a filter on category listing pages —
  internal-only specs can stay hidden from customers while still tracked.
- **`attribute_values`** — the selectable options for an attribute, e.g.
  Colour → Red, Blue.
- **`item_variant_attribute_values`** — links a variant to any number of
  attribute values as its option choices (Colour=Red, Size=Large).
  `UNIQUE(item_variant_id, attribute_id)` stops a variant claiming two
  values for one attribute.

## 4. Stock: A Single Append-Only Ledger

Every variant's stock — raw material, sub-assembly, or finished good —
lives in one table, `stock`. Nothing is ever updated in place; every
addition and every consumption is its own row, and a variant's current
stock is `SUM(receipt + production_output + return) − SUM(consumption)`.

- **Additions**: `receipt` (from a PO), `production_output` (from a
  production batch), `return` (a despatched line coming back).
- **Consumption**: `consumption` (fulfilling a sale, or consumed as a
  component in a production batch).
- **Cost basis is FIFO for every variant**, not just physically-divisible
  ones. A consumption row sets `source_stock_id`, a self-reference to
  whichever earlier addition row it drew from, chosen oldest-first. If one
  lot doesn't cover the full amount, consumption splits across multiple
  rows, each pointing at a different source lot. This applies uniformly —
  cost prices change between purchase orders even for simple countable
  items, so FIFO cost-matching isn't limited to batch-style materials.
- **`production_batch_id`** plays two roles depending on `entry_type`: on
  a `production_output` row it means "this batch made this stock"; on a
  `consumption` row it means "this batch consumed this stock as a
  component." `entry_type` disambiguates, so no separate column is needed.
- **`return` rows** carry the cost from the order line's own snapshotted
  `cost_price` (§6) rather than re-tracing the original consumption's
  source lot(s) — simpler, and correct since the snapshot already
  captured the right figure.

A single append-only ledger was chosen over a "current stock total +
separate history log" split: the latter is faster for a bare stock-level
lookup, but FIFO cost-matching has to walk the detailed log regardless of
which approach is used, so a cached total wouldn't remove ledger-walking
from the paths that actually need it — it would only add a second write
path that can drift out of sync with the truth. Whether store/location
tracking or a cached current-stock column are worth adding is a v4
question (§18), once real usage shows whether it's needed.

## 5. Suppliers & Purchasing

Each `item_variant` can be linked to one or more suppliers via
`item_variant_suppliers`, with one flagged `is_default`. Starting a new PO
for a variant pre-selects its default supplier. Item variants and asset
resources both flow through the same `purchase_orders`/`purchase_order_items`
mechanism; receiving a variant item writes a `receipt` row to the stock
ledger, receiving a resource activates it (stamping cost/date from the PO
line).

## 6. Sales Orders, Fulfillment & Returns

### Status lifecycle

| Status | Meaning |
|---|---|
| `draft` | Created just before the customer is redirected to the payment gateway, so the order id can be passed through. |
| `payment_pending` | Payment declined or errored. Not retried automatically — the customer's next attempt creates a new order. This status is a prompt for an admin to follow up. |
| `abandoned` | A storefront-originated (`channel = storefront`) order still `draft` after 2 hours, swept by a scheduled task. Manually-created orders are exempt. |
| `awaiting_despatch` | Paid, and every line currently has stock. |
| `unallocated` | Paid, but at least one line doesn't yet have stock. Automatically flips to `awaiting_despatch` once a PO receipt or production batch covers the shortfall. |
| `partially_despatched` | Among non-cancelled lines, some are despatched and some are still pending. |
| `completed` | Every non-cancelled line has been despatched. |
| `partially_returned` | Was `completed`; some (not all) despatched lines have since been returned. |
| `returned` | Was `completed`; every line has since been returned. |
| `cancelled` | Every line on the order was cancelled before despatch. |

Order status above `draft` is a derived summary of its lines' individual
statuses, not tracked independently — see line status below.

### Line status and despatch

Each `sales_order_item` carries its own `line_status`
(`pending`/`despatched`/`cancelled`/`returned`):

- **Despatch** — a per-line checkbox, shown only when stock exists for
  that line. Ticking it writes a `consumption` row to the stock ledger
  (FIFO-matched), snapshots `cost_price` on the line from that
  consumption, and flips `line_status` to `despatched`. Paid orders never
  consume stock automatically — only this explicit action does.
- **Cancel** — available on a `pending` line only (not yet despatched).
  No stock effect, since nothing was consumed yet. If every line on the
  order ends up cancelled, the order becomes `cancelled`.
- **Return** — available on a `despatched` line instead of cancel. Writes
  a `return` row to the stock ledger (cost from the line's snapshotted
  `cost_price`) and flips `line_status` to `returned`.

Cancelled lines are excluded from the "is this order done" calculation
entirely, so a mix of cancelled and despatched lines on the same order
resolves correctly: once every remaining (non-cancelled) line is
despatched, the order reaches `completed` regardless of how many others
were cancelled along the way.

Cancelling or returning a line only ever affects stock and status — it
never triggers a refund automatically. Refunds are a fully separate,
manually-initiated action (§7).

## 7. Payments & Refunds

`payments` holds both original payments and refunds, distinguished purely
by the sign of `amount` — a refund is a negative-amount row, not a
separate table. Every payment row can carry a `refunded_payment_id`
self-reference, set only on refund rows, pointing at the original payment
being refunded against.

Refunds are triggered manually from a payments list on the sales order
admin screen, against a specific existing (positive) payment row. The
admin enters an amount, validated against that payment's *remaining*
un-refunded balance (its original amount plus any refunds already issued
against it — since refunds are negative, this nets down automatically),
which then calls `PaymentGatewayInterface`'s refund capability using that
payment's own `gateway_reference` as the transaction to refund.

Payments flow through a swappable gateway abstraction —
`PaymentGatewayInterface` with `initiate()`, `handleCallback()`, and a
refund operation — so Global Payments' hosted iframe can be the first
implementation with Stripe or others added later purely by writing a new
implementation and flipping a config value, without touching checkout or
refund code.

## 8. Storefront

Public, unauthenticated catalogue of `sellable` variants, organised by the
category tree, filterable by attributes flagged `display_on_storefront`.
Checkout is guest-first: entering an email that matches a `customers` row
with a password prompts a login (with a "continue as guest" option);
otherwise checkout proceeds and offers to save a password afterward for
next time. A session cart holds selections until checkout creates the
`draft` order just before redirecting to payment.

## 9. Invoicing

No dedicated `invoices` table — the invoice number is simply
`sales_orders.id`. `sales_orders.invoice_issued_at` (nullable) is stamped
the first time an invoice is generated. PDFs are rendered on demand from
live order/customer/line-item data and streamed to the response rather
than stored, so there's no risk of a stale copy if an order is later
corrected.

## 10. Functional Scope (v1)

- Items, variants, BOM (multi-level, self-referencing)
- Categories (admin-managed tree, drag-and-drop reordering, storefront nav)
- Attributes (admin-managed, storefront visibility toggle, variant option
  choices)
- Resources (capital assets depreciated per hour, labour at an hourly rate)
- Stock ledger with universal FIFO cost-matching
- Suppliers, with a default per variant pre-selected on new POs
- Purchase orders (variants and asset resources)
- Manufacturing screen — production-planning view: every manufactured
  variant below its reorder threshold or with unmet order demand, with a
  suggested batch size and one-click batch creation
- Production batches
- Sales orders — storefront checkout or manual admin entry, full status
  lifecycle (§6), per-line despatch/cancel/return
- Payments — pluggable gateway, webhook-confirmed, manual refunds against
  specific payment lines
- Storefront — catalogue, cart, 1–2 page checkout, guest or account
- On-demand PDF invoicing
- Admin section covering all of the above

Out of scope for v1: role-based permissions (single admin account type),
cart abandonment recovery beyond the draft-order timeout, multi-currency
(GBP only), cascading multi-level requirements explosion.

## 11. Tech Stack

| Concern | Choice | Notes |
|---|---|---|
| Language | PHP 8.5+ | typed properties, enums, readonly, `match` |
| Framework | Slim 4 | `slim/slim` + `slim/psr7` |
| DI container | PHP-DI | PSR-11, autowiring |
| DB | MySQL 8 / MariaDB 10.6+ | |
| Migrations | Phinx | |
| Data access | Doctrine DBAL (query builder only, no ORM) | |
| Templating | Twig | separate `admin/` and `storefront/` trees |
| PDF generation | Dompdf | rendered on demand, not persisted |
| Payments | `PaymentGatewayInterface` abstraction | Global Payments HPP first, Stripe later via a new implementation + config switch |
| Testing | PHPUnit 11 | unit + integration + functional, `FakePaymentGateway` for checkout tests |
| Static analysis | PHPStan (level 6+), PHP-CS-Fixer (PSR-12) | |
| Config | `vlucas/phpdotenv` | |
| Logging | Monolog | PSR-3 |
| Source control / CI | Bitbucket (private, dev) → GitHub (public mirror) | Bitbucket Pipelines runs `phpunit`/`phpstan`/`php-cs-fixer` on every push; a pipeline step also mirrors the full history to GitHub via `git push --mirror` over an SSH deploy key (Bitbucket has no native mirror toggle). A separate GitHub Actions workflow is added on the GitHub side once public. |
| Auth | Session-based, two guards | `AdminAuthMiddleware` + optional `CustomerAuthMiddleware` |
| Local dev | DDEV | |
| Deployment | Docker | |
| Currency | GBP only | |

## 12. Directory Structure

```
config/
public/
  index.php
src/
  Controller/
    Admin/
    Storefront/
    Webhook/
  Domain/
    Item/                # items, variants, BOM tree, cost roll-up, cycle check, stock ledger, FIFO allocation
    Catalog/                # categories (tree, reordering), attributes, storefront browsing/filtering
    Resource/                # assets, labour, depreciation-rate calculation
    Manufacturing/          # low-stock/demand scan, production batch logic
    Order/                    # status lifecycle, despatch/cancel/return
    Invoice/                    # on-demand PDF rendering
    Payment/                      # PaymentGatewayInterface + implementations, refunds
  Repository/
  Entity/
  Middleware/
  Support/
templates/
  admin/
  storefront/
migrations/
tests/
  Unit/
  Integration/
  Functional/
```

## 13. Database Schema

```
items
  id, name, description, active, notes

item_variants
  id, item_id FK, sku, name, unit, reorder_threshold,
  sellable, sale_price NULL, status (active | discontinued), notes
  -- manufactured vs purchased is derived: manufactured iff component
  --   variant_bom_lines rows exist for this variant

categories
  id, parent_category_id FK NULL, name, slug, sort_order, active, notes

item_category_assignments
  id, item_id FK, category_id FK

attributes
  id, name, display_on_storefront, sort_order, notes

attribute_values
  id, attribute_id FK, value, sort_order, notes

item_variant_attribute_values
  id, item_variant_id FK, attribute_id FK, attribute_value_id FK
  -- UNIQUE(item_variant_id, attribute_id)

variant_bom_lines
  id, variant_id FK, line_type (component | resource),
  component_variant_id FK NULL, resource_id FK NULL,
  quantity NULL,                 -- component lines only
  hours_used NULL,                 -- resource lines only (planning estimate)
  notes

resources
  id, name, type (asset | labour), status (on_order | active | retired),
  purchase_cost NULL, expected_lifetime_hours NULL,   -- asset only
  hourly_rate NULL,                                     -- labour only
  notes
  -- asset rate/hr = purchase_cost / expected_lifetime_hours (derived, never stored)

suppliers
  id, name, contact_info

item_variant_suppliers
  id, item_variant_id FK, supplier_id FK, is_default, supplier_sku NULL, notes
  -- app-level check: at most one is_default = true per item_variant

purchase_orders
  id, supplier_id FK, order_date, status (draft|ordered|received|cancelled), notes

purchase_order_items
  id, purchase_order_id FK, item_variant_id FK NULL, resource_id FK NULL,
  quantity, unit_cost, received_at NULL

stock
  id, item_variant_id FK,
  entry_type (receipt | production_output | consumption | return),
  quantity,                       -- always positive; entry_type gives direction
  cost NULL,                       -- receipt / production_output / return rows
  source_stock_id FK NULL,          -- self-reference; FIFO-selected source lot for consumption rows
  purchase_order_item_id FK NULL,    -- receipt rows
  production_batch_id FK NULL,        -- production_output (produced this) OR consumption
                                         -- (consumed as a component) — entry_type disambiguates
  sales_order_item_id FK NULL,          -- consumption rows (despatch) and return rows
  occurred_at, notes

customers
  id, name, email (unique), phone, address, password_hash NULL, notes

sales_orders
  id, customer_id FK, order_date, channel (storefront | manual),
  status (draft|payment_pending|abandoned|awaiting_despatch|unallocated|
          partially_despatched|completed|partially_returned|returned|cancelled),
  invoice_issued_at NULL, notes

sales_order_items
  id, sales_order_id FK, item_variant_id FK, quantity, unit_price,
  cost_price NULL,                 -- snapshotted from the FIFO-matched stock
                                       -- consumption row(s) at despatch time
  line_status (pending | despatched | cancelled | returned)

production_batches
  id, item_variant_id FK, quantity_produced, produced_at, notes

resource_usage
  id, sales_order_item_id FK NULL, production_batch_id FK NULL,
  resource_id FK, hours_used, used_at, note
  -- actual logged duration, entered at production/despatch time

payments
  id, sales_order_id FK, gateway, gateway_reference,
  amount,                          -- signed; negative = refund
  currency, status (pending | authorized | captured | failed),
  refunded_payment_id FK NULL,       -- self-reference, set only on refund rows
  raw_response (JSON), created_at, updated_at

users
  id, email, password_hash, created_at, last_login_at
```

## 14. Core Workflows

- **Manage categories** (admin) → drag-and-drop reorder persists
  `sort_order` among affected siblings only → storefront nav rebuilds from
  the same ordered tree.
- **Manage attributes** (admin) → define attributes and values → toggle
  storefront visibility → assign values to variants as option choices.
- **Order a new variant or asset resource** → must exist as a catalogue
  row first → add to a PO, pre-selecting the variant's default supplier →
  receiving writes a `receipt` stock row, or activates the resource.
- **Build a BOM** → add component and/or resource lines → cycle check on
  component lines at save time → domain layer rolls up estimated cost.
- **Storefront checkout** → session cart → email entered, login prompt if
  it matches a registered `customers` row (guest option always available)
  → create the order as `draft` → `PaymentGatewayInterface::initiate()` →
  gateway iframe/redirect → webhook callback → on success, check stock for
  every line: all covered → `awaiting_despatch`; otherwise →
  `unallocated`. On decline/error → `payment_pending`. A scheduled task
  sweeps storefront `draft` orders older than 2 hours to `abandoned`.
- **Manufacturing screen** (admin) → every manufactured variant at or
  below its reorder threshold, or with unmet order demand → suggested
  batch size → one-click batch creation.
- **Record a production batch** → explode the BOM one level × quantity →
  FIFO-allocate each component from earlier stock rows → hard-fail with a
  clear shortage message if insufficient (no auto-cascading) → on
  success, write one `production_output` row for the variant produced,
  `consumption` rows per component (split across source lots if needed),
  and `resource_usage` rows → re-check any `unallocated` orders waiting on
  the variant produced and flip qualifying ones to `awaiting_despatch`.
- **Despatch a line** (admin) → shown only when stock exists for that
  line → writes a `consumption` stock row (FIFO-matched), snapshots
  `cost_price`, flips `line_status` to `despatched` → order status
  re-derived from its lines (§6).
- **Cancel a line** (admin, `pending` lines only) → flips `line_status` to
  `cancelled`, no stock effect → order status re-derived; `cancelled` if
  every line ends up cancelled.
- **Return a line** (admin, `despatched` lines only) → writes a `return`
  stock row using the line's snapshotted `cost_price` → flips
  `line_status` to `returned` → order status re-derived.
- **Issue a refund** (admin, from the order's payments list) → select an
  existing payment, enter an amount validated against its remaining
  un-refunded balance → calls the gateway's refund operation using that
  payment's `gateway_reference` → writes a negative-amount `payments` row
  linked back via `refunded_payment_id`.
- **Generate invoice** → render on demand from live order data via Dompdf,
  using `sales_orders.id` as the invoice number → stamp
  `invoice_issued_at` on first generation.

## 15. Testing Strategy

- **Unit** (`tests/Unit`) — BOM cost roll-up (recursive, multi-level),
  cycle detection, FIFO allocation across the stock ledger (including
  splitting a single consumption across multiple source lots), order
  status derivation from line statuses, low-stock evaluation across
  levels. No DB — these are exactly the rules that benefit most from
  being pure and framework-free.
- **Integration** (`tests/Integration`) — repositories against a test
  MySQL schema in DDEV, transactions rolled back per test.
- **Functional** (`tests/Functional`) — PSR-7 requests through
  `App::handle()`; checkout and refund tests bind a `FakePaymentGateway`.
- CI: Bitbucket Pipelines — `phpunit`, `phpstan`, `php-cs-fixer --dry-run`
  on every push.

## 16. Roadmap: v2

- Frontend basket and reserved-stock handling
- Margin percentage on the sales order admin screen (now that
  `cost_price` and `unit_price` are both captured per line in v1)
- Basic sales and payment reports
- Sales order notes
- Sending invoice PDFs by email

## 17. Roadmap: v3

- A report builder — covering manufacturing, sales, payments, categories,
  and similar areas
- A workflow/node-based automation builder — e.g. adding a sales order
  note automatically, or re-sending the invoice email as a chaser X days
  after `invoice_issued_at`

## 18. Roadmap: v4

- Assess whether the stock ledger's performance holds up in real use
- If needed, add store/location tracking to stock (the ledger extends
  naturally with a nullable `location_id`, without restructuring what's
  already there)

## 19. Implementation notes (decisions made while building v1)

The spec left these open; the code settles them as follows.

- **Unit costs everywhere.** `stock.cost` is per unit. `sales_order_items.cost_price` is the per-unit weighted cost of the lots a despatch consumed (total consumed cost / quantity). A `return` row reuses that figure.
- **Return while other lines are still pending.** The order stays `partially_despatched`. `partially_returned` and `returned` apply only once no line is pending.
- **Stock changes re-check waiting orders both ways.** After a receipt, production batch, despatch or return, paid orders with a pending line for that variant are re-derived, so `unallocated` flips to `awaiting_despatch` and back. There is no stock reservation in v1 (see §16), so each order is checked against total stock.
- **Manual orders** start as `draft`; "Mark paid" records a captured payment with gateway `manual` for the full total, then derives status. Manual payments are refunded without a gateway call.
- **Gateway references.** The pending payment row carries our request reference; on success `gateway_reference` is replaced by the gateway's reference (Global Payments: `ORDERID|PASREF|AUTHCODE`, all three are needed to rebate). Callbacks are idempotent.
- **Production resource usage** is logged at production time (BOM hours x quantity unless overridden). Despatch does not log resource usage.
- **Hosted payment iframe.** Checkout posts the gateway fields into an iframe; the webhook response breaks out of the frame to the order page.
- **`src/Domain/*Service` classes** depend on repositories. The pure rule classes next to them do not, and carry the unit tests.
