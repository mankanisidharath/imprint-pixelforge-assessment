# Pixelforge generation assessment

A Laravel and Inertia application that submits image generations to Pixelforge, estimates charges, receives signed webhooks, and reconciles missed updates. Users can create generations and view their history. The installed dependencies are Laravel 12 and Inertia 2; Laravel 13 and Inertia 3 remain the target once the dependency upgrade is unblocked.

## Local setup

1. Install PHP and JavaScript dependencies: `composer install` and `npm install`.
2. Copy `.env.example` to `.env` and run `php artisan key:generate`. Configure a local database and run `php artisan migrate`.
3. Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`. `PIXELFORGE_BASE_URL` defaults to the provider API URL. Keep `QUEUE_CONNECTION=database` and run `php artisan queue:work`.
4. Run `php artisan serve`, `npm run dev`, and `php artisan schedule:work` in separate terminals. Expose the app through a tunnel and configure Pixelforge to send webhooks to its public URL at `/webhooks/pixelforge`.

## Money

Amounts are stored as integer nanodollars (1 USD = 1,000,000,000 nano). For each token type, convert its USD-per-million rate to nano per token and sum `tokens × nano_per_token`. The customer charge is provider cost plus 25% markup, rounded up to the next nanodollar. Exact amounts retain nine decimal places; shorter rounding is for display only. Estimates use the provider's token ranges; settlement uses actual usage and fixed generation rates. No balance ledger or payment collection is implemented.

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

## Tests and checks

Tests use isolated in-memory SQLite via `phpunit.xml`; they must never point to a hosted database.

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
- Laravel 13/Inertia 3 upgrade remains blocked by dependency security and compatibility constraints.

## Design decisions

TODO: Author to add design decisions.
