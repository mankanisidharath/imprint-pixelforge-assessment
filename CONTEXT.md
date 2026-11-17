# Session Context — read this first, every session

Read this, then `DECISIONS.md`, `ARCHITECTURE.md`, and `FLOW.md` before touching code.

## Right now

Bootstrap is complete. The Laravel 13/Inertia 3 dependency upgrade is blocked by Composer security advisories and an installed Pest Laravel plugin that supports only Laravel 11/12.

## Done

- Added Pixelforge data contracts, persistence, route stubs, a provider client, and focused tests.
- Added living documentation and Git workflow conventions.
- Initialized Git with an empty `main` branch and configured the `origin` remote.

## In progress

- Commit the initial repository state after final verification.

## Broken

- `composer require laravel/framework:^13.0 inertiajs/inertia-laravel:^3.0 --update-with-all-dependencies` is blocked by active Composer security advisories for every available Laravel 13 release.

## Do not

- Do not bypass Composer security blocking to force Laravel 13.
- Do not implement money arithmetic, settlement, or webhook verification until their later phases.
- Do not rename the Pixelforge contracts created for parallel work.

## Constraint

There is no AI time or budget limit. Graph tooling, graphify, grep hooks, and auto-memory are intentionally not configured.
