# PHP E-Commerce Store

PHP 8.5 / Slim 4 app for a small maker business: items, variants and a multi-level BOM, an append-only FIFO stock ledger, purchasing, production, sales orders with per-line despatch/cancel/return, payments and manual refunds, on-demand PDF invoices, and a public storefront. Spec: `docs/spec.md`. Build order: `docs/build-plan.md`. Project rules: `CLAUDE.md`.

## Run it (DDEV)

```bash
ddev start
ddev composer install
cp .env.example .env            # DB defaults already match DDEV
ddev exec vendor/bin/phinx migrate
ddev exec vendor/bin/phinx migrate -e testing
ddev exec php bin/create-admin.php you@example.com 'a-long-password'
```

Admin: `/admin`. Storefront: `/`. With `PAYMENT_GATEWAY=fake` checkout completes immediately with a successful fake payment; switch to `globalpayments` and fill the `GP_*` variables for the real hosted page.

## Checks

```bash
composer check          # php-cs-fixer (dry run), phpstan level 6, phpunit
composer test:unit      # pure domain tests, no database
vendor/bin/phpunit --testsuite integration   # needs the migrated db_test schema
```

`php bin/run-unit-tests.php [Unit|Functional]` runs the same test files without Composer, via a tiny PHPUnit stand-in in `tests/shim/`. It exists for environments where `composer install` isn't possible; use PHPUnit otherwise.

## Cron

```
*/15 * * * * php /path/to/bin/sweep-abandoned.php
```

Marks storefront orders still `draft` after 2 hours as `abandoned`. Manual orders are exempt.

## Layout

`src/Domain/*` holds the business rules. Pure classes (BOM cost roll-up, cycle checker, FIFO allocator, order status deriver, batch planner, refund calculator, category tree) have no database or HTTP dependency. The `*Service` classes next to them orchestrate repositories inside transactions. Controllers only parse input, call a service and render. Repositories use Doctrine DBAL's query builder (no ORM) and hydrate plain entities.

## Mirroring

`bitbucket-pipelines.yml` runs the checks on every push and, on `main`, mirrors to GitHub with `git push --mirror`. Set the repository variable `GITHUB_MIRROR_URL` and add an SSH key under Pipelines > SSH keys. A GitHub Actions workflow is a separate, later addition on the GitHub side only.
