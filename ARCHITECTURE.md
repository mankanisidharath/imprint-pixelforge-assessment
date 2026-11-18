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
- `app/Services/CostCalculator.php` and `app/Services/GenerationSettler.php`: Reserved application-service contracts.
- `app/Jobs`: Durable background-job contracts.
- `app/Features`: Empty boundary for future feature-owned behavior. Do not move existing Pixelforge contracts here without a coordinated change.
- `resources/js/pages`: Inertia Vue pages.
- `docs`: Provider facts and handoff contracts.
- `public/user-guide.html`: Locally served guide linked from the sidebar.

## Data model

- `users` has many `generations`.
- `generations` records submission state, token usage, provider cost, customer charge, image delivery, and provider failures.
- `webhook_events` records at-least-once provider delivery attempts by unique event ID.

Money values are integer nanodollars. A future ledger must be append-only, use idempotency keys, and lock rows while changing balances.
