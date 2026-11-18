# Session Context — read this first, every session

Read this, then `DECISIONS.md`, `ARCHITECTURE.md`, and `FLOW.md` before touching code.

## Right now

Shared Pixelforge definitions, request setup, and navigation are consolidated. All 80 tests, Pint, targeted ESLint checks, and the frontend build pass. The Laravel 13/Inertia 3 dependency upgrade remains blocked by Composer security advisories and the installed Pest Laravel plugin.

Local `main` includes the session's money calculations, settlement, shared-definition refactor, and billing documentation. Money calculations are committed as `555c187`, and settlement as `8355eff`. The remaining session changes were committed on `feat/pixelforge-shared-contracts` and integrated by fast-forward. The latest verified GitHub `main` remains at bootstrap commit `a8be42e`. Remote publication remains pending.

## Done

- Added Pixelforge data contracts, persistence, route stubs, a provider client, and focused tests.
- Implemented exact nanodollar arithmetic, estimates, settlement, webhook verification, and reconciliation.
- Shared token keys between costing and settlement and generation options between the controller and factory.
- Consolidated Pixelforge request configuration and navigation link definitions.
- Reused the shared navigation item type in `NavMain` and corrected its link and active-state field to `href`.
- Shared signed-webhook test setup and corrected the unsigned CSRF test.
- Documented integer markup rounding, the estimate-to-final-charge flow, and the current Git integration state.
- Integrated the reviewed session work into local `main` with its commit history preserved.
- Added living documentation and Git workflow conventions.
- Committed the initial repository and configured the `origin` remote.

## In progress

No active implementation work. Publishing local `main` to GitHub remains pending.

## Broken

- `composer require laravel/framework:^13.0 inertiajs/inertia-laravel:^3.0 --update-with-all-dependencies` is blocked by active Composer security advisories for every available Laravel 13 release.
- The existing TypeScript configuration refers to unresolved `vue/tsx` and `./resources/js/types` entries. `vue-tsc --noEmit` stops on these entries. The production Vite build and targeted ESLint checks pass.

## Do not

- Do not bypass Composer security blocking to force Laravel 13.
- Do not rename the Pixelforge contracts created for parallel work.

## Constraint

There is no AI time or budget limit. Graph tooling, graphify, grep hooks, and auto-memory are intentionally not configured.
