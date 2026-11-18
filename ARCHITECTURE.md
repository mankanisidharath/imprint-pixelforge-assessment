# Architecture — imprint-pixelforge-assessment

`AGENTS.md` has operating rules, `FLOW.md` has execution traces, and `DECISIONS.md` records rationale.

## Project Charter

- **Product:** Assessment project for webhook and money-correctness testing.
- **Users:** No user-facing product users.
- **Core loop:** None.
- **Launch state:** Not applicable.
- **Target stack:** PHP 8.2+, Laravel 13, Inertia 3, Vue 3, TypeScript, Tailwind CSS, shadcn-vue, Fortify, Pest, PostgreSQL.
- **Current dependency state:** Laravel 12 and Inertia 2 remain installed until Composer permits a secure Laravel 13 upgrade.
- **Repository:** One repository. Cross-repository work is handed off as a contract file or paste-ready prompt.
- **Data:** PostgreSQL is the target database. Tests use an isolated local test database.
- **Auth:** Laravel Fortify. No local authentication bypass is configured.
- **Hosting:** Laravel Cloud. Deployment policy is not configured yet.
- **LLM:** None.

## System boundary

```text
Inertia Vue client
    │ authenticated web requests
    ▼
Laravel application
    ├─► PostgreSQL
    └─► Pixelforge API and webhooks
```

## Modules

- `app/Models`: Central Eloquent models and database relationships.
- `app/Http`: Controllers and Inertia request boundaries.
- `app/Services/Pixelforge.php`: Thin provider HTTP client.
- `app/Services/CostCalculator.php` calculates provider costs, markup, and estimates in integer nanodollars.
- `app/Services/GenerationSettler.php` settles snapshots transactionally for webhooks and reconciliation.
- `app/Support/Money.php` parses decimal strings and formats exact nanodollar amounts.
- `app/Jobs`: Durable background-job contracts.
- `app/Features/Pixelforge/TokenType.php` defines the token keys and billable-key list shared by costing and settlement.
- `app/Features/Pixelforge/GenerationOptions.php` defines the options, defaults, and limits shared by the controller and factory.
- `resources/js/pages`: Inertia Vue pages.
- `resources/js/lib/navigation.ts` defines shared dashboard and external navigation links.
- `resources/js/features/generations/navigation.ts` defines generation links shared by the sidebar and page breadcrumbs.
- `resources/js/types/index.ts` defines the navigation item shape consumed by navigation components.
- `docs`: Provider facts and handoff contracts.

## Data model

- `users` has many `generations`.
- `generations` records submission state, token usage, provider cost, customer charge, image delivery, and provider failures.
- `webhook_events` records at-least-once provider delivery attempts by unique event ID.

Money values are integer nanodollars. A future ledger must be append-only, use idempotency keys, and lock rows while changing balances.

The calculator returns provider-cost estimate bounds. The planned estimate endpoint applies markup to each bound for its user-facing preview. On success, settlement calculates actual provider cost from reported usage and records the marked-up amount in `charge_nano`. Failed generations record zero cost and charge. Balance debiting remains a future phase.
