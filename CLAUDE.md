# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

Filament & Print Shop Manager — a PHP web app for running a small
manufacturing/selling business (stock, production, purchasing, sales
orders, payments, invoicing) plus a public storefront. Built as a
learning vehicle for modern PHP practices, in deliberate contrast to a
legacy Slim 2 / MySQL / raw-PDO codebase used at the author's day job.

**Status: pre-implementation.** Planning is complete; nothing has been
built yet. Full spec: `docs/spec.md`. Suggested build order:
`docs/build-plan.md`. Read both before writing code.

## Tech stack (non-negotiable — see docs/spec.md §11 for the full table)

- PHP 8.5+, Slim 4, PHP-DI
- MySQL 8 / MariaDB 10.6+, Phinx migrations
- **Doctrine DBAL as a query builder only — no ORM.** Repositories
  hand-hydrate plain PHP entities. Deliberate choice for testability and
  to stay close to SQL; don't introduce Doctrine ORM, Eloquent, or similar.
- Twig for server-rendered views (separate `admin/` and `storefront/`
  template trees) — no SPA framework, no API-first architecture.
- Dompdf for PDFs, rendered on demand — nothing persisted to disk.
- PHPUnit 11, PHPStan (level 6+), PHP-CS-Fixer (PSR-12).
- **Bitbucket Pipelines is the CI system, not GitHub Actions.** Developed
  on Bitbucket, mirrored to GitHub for public visibility. A GitHub Actions
  workflow is a separate, later addition on the GitHub side only.
- DDEV for local dev, Docker for deployment.
- GBP only, no multi-currency.

## Core architectural principles

These are the load-bearing ideas of this codebase. Don't casually
"simplify" or reinvent them — they were chosen deliberately after working
through the alternatives. Full rationale is in `docs/spec.md`.

1. **Everything is an item variant.** No separate schema for raw
   materials vs. sub-assemblies vs. finished sellable goods —
   `items` + `item_variants` covers all of it. Whether a variant is
   "manufactured" or "purchased" is *derived* from whether it has BOM
   component lines, never stored as a flag.
2. **Self-referencing BOM.** `variant_bom_lines` lets a variant's recipe
   reference other variants, enabling arbitrary-depth manufacturing (raw
   material → sub-assembly → finished good) without new tables. Cost
   roll-up is a recursive PHP function over the loaded tree, computed in
   the domain layer — not a recursive SQL query. Cycle prevention is an
   application-layer check, not a DB constraint.
3. **Stock is a single append-only ledger** (`stock`), not mutable
   "current quantity" fields. Every addition and consumption is its own
   row; current stock is always `SUM(additions) − SUM(consumption)`. FIFO
   cost-matching applies to *every* variant, not just physically-divisible
   ones — cost prices change between purchase orders regardless of what's
   being bought.
4. **Sales order status is always derived**, never stored as an
   independent choice above `draft`. It's a summary of each line's own
   `line_status` (pending/despatched/cancelled/returned). Get the
   derivation logic right (spec §6) rather than adding new order-level
   states for edge cases — the line-level model already handles them.
5. **Payment and stock consumption are decoupled.** Paying for an order
   never consumes stock; only an explicit per-line despatch action does.
   Refunds are a fully separate, manually-triggered action against a
   specific payment record — never automatic on cancel/return.
6. **Refunds are negative-amount rows in `payments`**, not a separate table.
7. **Categories and the BOM use the same self-referencing tree pattern**
   deliberately — if writing cycle-detection or tree-walking code for one,
   check whether it can be shared with the other.

## Conventions

- Controllers stay thin: parse the request, call a domain service, build
  the response. Business logic lives in `src/Domain/`, framework-free and
  unit-testable without a DB or HTTP context.
- Test pyramid: `tests/Unit` (pure domain logic, no DB — BOM cost roll-up,
  cycle detection, FIFO allocation, order status derivation — these
  should be the most thoroughly tested code in the repo), `tests/Integration`
  (repositories against a real DDEV MySQL schema, each test in a
  rolled-back transaction), `tests/Functional` (HTTP via Slim's
  `App::handle()`, binding a `FakePaymentGateway` for anything
  checkout/payment-related).
- Payment gateways implement `PaymentGatewayInterface` and are swapped via
  DI container config, never referenced concretely in controllers.
- Never commit secrets. `.env` is gitignored; `.env.example` holds
  placeholders only. This matters more than usual here since the repo's
  full history gets mirrored to a public GitHub repo.
- Check `docs/spec.md` before adding a dependency that might duplicate
  something already chosen (e.g. a second templating engine, an HTTP
  router, an ORM).

## Where to look for detail

- `docs/spec.md` — the full spec: complete schema, every table's columns
  and purpose, status lifecycles, workflows, testing strategy.
- `docs/build-plan.md` — suggested implementation order and what depends
  on what.
- `docs/spec.md` §16–18 — v2/v3/v4 roadmap. Not in scope for the initial
  build; don't implement early unless asked.
