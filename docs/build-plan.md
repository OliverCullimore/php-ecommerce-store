# Build Plan

A dependency-ordered suggestion for implementation order. Not
prescriptive — reorder if a different sequence makes more sense once
you're in it — but later phases generally assume earlier ones exist.

## Phase 0 — Scaffolding
- Composer init: Slim 4 + PHP-DI + Doctrine DBAL + Twig + Dompdf +
  Monolog + phpdotenv
- DDEV config (PHP 8.5, MySQL 8/MariaDB 10.6+)
- Directory structure per spec §12
- PHPUnit, PHPStan, PHP-CS-Fixer configured and passing on an empty project
- Bitbucket repo + Pipelines running the above on every push (this is
  itself a learning goal — see CLAUDE.md)
- Phinx configured, migrations directory ready

## Phase 1 — Core domain: items, variants, BOM
- Migrations: `items`, `item_variants`
- Migrations: `variant_bom_lines`, `resources`
- Domain: BOM tree loading, recursive cost roll-up, cycle detection —
  unit test these thoroughly before anything else touches them
- Admin CRUD: items, variants, resources, BOM editor

## Phase 2 — Catalogue
- Migrations: `categories`, `item_category_assignments`, `attributes`,
  `attribute_values`, `item_variant_attribute_values`
- Domain: category tree assembly (reuse the BOM's tree-walking approach)
- Admin: category tree management with drag-and-drop reordering,
  attribute management with the storefront-visibility toggle

## Phase 3 — Stock ledger
- Migration: `stock`
- Domain: FIFO allocation (including splitting across multiple source
  lots), current-stock derivation query, low-stock evaluation — unit test
  heavily, this is the highest-risk correctness area in the app

## Phase 4 — Purchasing
- Migrations: `suppliers`, `item_variant_suppliers`, `purchase_orders`,
  `purchase_order_items`
- Workflow: receiving a PO item writes a `receipt` stock row or activates
  a resource
- Admin: supplier management, PO creation (pre-selecting default
  supplier), receiving

## Phase 5 — Manufacturing
- Migrations: `production_batches`, `resource_usage`
- Domain: BOM explosion for a production run, shortage handling
  (hard-fail, no cascading)
- Admin: manufacturing screen (low-stock + unmet-demand view), production
  batch creation

## Phase 6 — Customers & sales orders
- Migrations: `customers`, `sales_orders`, `sales_order_items`
- Domain: order status derivation from line statuses (spec §6) — unit
  test the full state matrix, including mixed cancelled/despatched lines
- Admin: manual order creation, order detail view, despatch/cancel/return
  actions

## Phase 7 — Storefront (no payment yet)
- Public routes: catalogue, category browsing with attribute filters,
  product detail with variant/attribute picker, session-based cart
- Guest/account checkout flow up to (but not including) the payment step

## Phase 8 — Payments
- `PaymentGatewayInterface`, `FakePaymentGateway` for tests
- Global Payments HPP implementation, webhook handling
- Wire into checkout: draft order → initiate → webhook → status transition
- Migration: `payments`
- Admin: payments list on the order screen, manual refund action

## Phase 9 — Invoicing
- On-demand PDF rendering from order data, `invoice_issued_at` stamping

## Phase 10 — Auth
- Admin session auth (`AdminAuthMiddleware`)
- Optional customer accounts (`password_hash` on `customers`,
  `CustomerAuthMiddleware`), login-prompt-on-matching-email at checkout

## Phase 11 — Scheduled tasks
- Draft-order abandonment sweep (2 hour timeout, storefront-only)

## Ongoing throughout
- GitHub mirror step in Pipelines, once there's something worth mirroring
- Keep `docs/spec.md` as the source of truth — if implementation reveals
  the spec needs to change, update the doc in the same change, don't let
  them drift apart
