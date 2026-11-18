# Pixelforge generation assessment

A Laravel and Inertia application that submits image generations to Pixelforge, estimates charges, receives signed webhooks, and reconciles missed updates. Users can create generations and view their history. The installed dependencies are Laravel 12 and Inertia 2; Laravel 13 and Inertia 3 remain the target once the dependency upgrade is unblocked.

## Local setup

1. Install PHP and JavaScript dependencies: `composer install` and `npm install`.
2. Copy `.env.example` to `.env` and run `php artisan key:generate`. Configure a local PostgreSQL database and run `php artisan migrate`.
3. Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`. `PIXELFORGE_BASE_URL` defaults to the provider API URL. Keep `QUEUE_CONNECTION=database`.
4. Run `composer run dev` and `php artisan schedule:work` in separate terminals. The Composer command starts the app, Vite, logs, and a queue listener; a separate `php artisan queue:work` can be used as the worker instead.
5. Expose the app through a tunnel such as ngrok and configure Pixelforge to send webhooks to the public URL at `/webhooks/pixelforge`.

## Money

Amounts are stored as integer nanodollars (1 USD = 1,000,000,000 nano). For each token type, convert its USD-per-million rate to nano per token and sum `tokens × nano_per_token`. The customer charge is provider cost plus 25% markup, rounded up to the next nanodollar. Exact amounts retain nine decimal places; shorter rounding is for display only. Estimates use the provider's token ranges; settlement uses actual usage and the rates in the provider snapshot. No balance ledger or payment collection is implemented.

## State and idempotency

Generations progress from queued to processing to succeeded or failed. Status ranks prevent older snapshots from moving a generation backwards; provider final states remain final. A local submission failure without a provider ID can be superseded by a later provider snapshot. Webhook event IDs have a unique index, and settlement locks the generation row and uses the same `GenerationSettler::apply` function for submissions, webhooks, and reconciliation. A local UUID in provider metadata allows an early webhook to find the row.

## Submission and errors

| Provider response | Handling |
| --- | --- |
| 202 | Apply the returned generation snapshot. |
| Concurrency or rate limit | Release the job using the provider delay and retry. |
| Overload, server error, or connection error | Retry with backoff; fail locally after five failures. |
| Daily limit, content policy, or validation error | Fail locally with a message and zero charge. |
| Other rejection | Log and fail locally with zero charge. |

Submissions use the local generation UUID as the provider idempotency key. The scheduler runs `generations:reconcile` every minute: it polls up to 20 accepted unfinished generations older than two minutes and times out unaccepted submissions older than 15 minutes. The history page also polls while visible generations are unfinished.

## Data and analytics

The documented production setup uses ClickHouse Managed Postgres for transactional state and money, including row locks, transactions, and unique indexes. The `generations` table is replicated into ClickHouse database `analytics` using ClickPipes Postgres CDC for read-only analysis; the application does not write to ClickHouse.

[Analytics queries](docs/ANALYTICS.md) cover daily spend, cost and profit by UTC completion day, failure rate by quality and size, processing-time percentiles, and estimate accuracy. Local PostgreSQL can be used for development and tests.

## Tests and checks

Tests run on SQLite by default, which ignores `FOR UPDATE`; the row lock is verified by design and manual testing, not by an automated concurrency test. Next step: run CI against PostgreSQL.

To run against PostgreSQL, create a disposable local test database and supply its local credentials explicitly; tests refresh its tables. Never use a hosted or development database.

```sh
env -u DB_URL DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
  DB_DATABASE=pixelforge_test DB_USERNAME=YOUR_LOCAL_USER DB_PASSWORD=YOUR_LOCAL_PASSWORD \
  php artisan test
```

Manual verification was reported through a tunnel against the real Pixelforge API, locally and on the deployed app: `[succeed]`, `[fail]`, `[duplicate]`, `[slow]`, `[blocked]`, `[overloaded]`, a resent webhook, concurrency limit, lost-webhook reconciliation, reference images, and a nine-decimal match with `GET /v1/usage`. A true out-of-order delivery and the per-minute rate limit were not manually tested. Automated coverage exercises out-of-order events in `tests/Feature/WebhookTest.php` and rate-limit responses in `tests/Feature/SubmitGenerationTest.php` and `tests/Feature/ReconcileTest.php`.

```sh
php artisan test
vendor/bin/pint --test
npm run lint
npm run type-check
npm run build
```

For a disposable migration check, run `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --force`.

## Limitations and next steps

- Generated images are hot-linked; provider URLs expire after 24 hours.
- The history page uses polling instead of websockets.
- Billing balances and an append-only ledger are not implemented.
- The admin report is not implemented. Copy `webhook_events` to ClickHouse to analyse out-of-order arrivals and retries; duplicate deliveries are discarded before insertion.
- Read analytics from PostgreSQL through the `pg_clickhouse` extension as a future step.
- Laravel 13/Inertia 3 upgrade remains blocked by dependency security and compatibility constraints.

## Design decisions

1. **Money:** Costs can be fractions of a cent and `/v1/usage` reports nine exact decimals. Integer nanodollars and whole nano-per-token rates allow integer cost multiplication; the UI converts to a number only for display.
2. **Markup:** The 25% markup is rounded up to a whole nanodollar and stored at that precision. The UI displays four decimals, with the exact final charge in a tooltip; rounding each charge to cents would turn $0.014 into $0.02.
3. **Estimates:** The server calculates a range using the pricing response's text variance (15% in the documented pricing) and quality-specific reasoning bounds. Both marked-up bounds are stored, and the button displays the maximum when an estimate is available.
4. **Rates:** Settlement calculates cost from the successful provider snapshot's `rates`, rather than fetching current pricing. The first snapshot's rates are also saved on the generation; the provider documents generation rates as fixed at creation.
5. **Settlement:** `GenerationSettler::apply` handles submission responses, webhooks, and reconciliation. Status rank orders queued, processing, and final states because webhooks have no sequence number; provider final states do not change.
6. **Webhook deduplication:** `insertOrIgnore` and the unique `webhook_events.event_id` index record each event at most once in the settlement transaction. Outcomes include `applied`, `stale`, `final`, and `unknown_generation`; an exception rolls back the event and settlement together.
7. **Concurrency:** Settlement locks the generation row with `lockForUpdate` inside a transaction so competing webhook and poll updates cannot both apply a final state.
8. **Inline webhook:** Signature verification, event insertion, and settlement happen in the request without a queued job. It is designed to finish well inside Pixelforge's 10-second limit: one short transaction and no outbound calls.
9. **Submission identity:** The local generation UUID is both the `Idempotency-Key` and `metadata.local_id`. Provider idempotency protects retries, and metadata lets an early webhook locate the local row.
10. **Errors:** Concurrency and per-minute limits release the job for retry; the job has a 10-minute retry deadline. Connection and server errors use exponential backoff and fail after five such failures; daily limit, content policy, and validation errors fail immediately. Local submission failures say “You were not charged.”
11. **Reconciliation:** `generations:reconcile` runs every minute and polls up to 20 accepted unfinished generations older than two minutes because webhook delivery is not guaranteed. Unaccepted queued submissions older than 15 minutes fail locally.
12. **Late provider result:** A local failure without a provider ID can be superseded by a provider snapshot, which can establish the actual billing outcome.
13. **Browser updates:** The history page polls the application every three seconds while a displayed generation is queued or processing, avoiding a WebSocket service.
14. **Images:** The app displays provider URLs without copying images to storage; those URLs expire 24 hours after completion.
