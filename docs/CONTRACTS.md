# Pixelforge foundation contracts

## Configuration

`config/services.php` defines:

```php
'pixelforge' => [
    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),
    'key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),
    'markup_percent' => 25,
],
```

`.env.example` includes empty `PIXELFORGE_BASE_URL`, `PIXELFORGE_API_KEY`, and `PIXELFORGE_WEBHOOK_SECRET` values.

## Generation status

`App\Enums\GenerationStatus` is a string-backed enum with `Queued = 'queued'`, `Processing = 'processing'`, `Succeeded = 'succeeded'`, and `Failed = 'failed'`.

`rank(): int` returns 0 for queued, 1 for processing, and 2 for succeeded or failed. `isFinal(): bool` is true for succeeded and failed.

## Persistence

`generations` columns:

- `id` UUID primary key; `user_id` foreign ID constrained with cascade deletion; nullable unique `provider_id`
- `prompt` text; `size` string(16); `quality` string(8); `reference_images` JSON
- `status` string(16), default `queued`; nullable `status_note`; `submit_failures` unsigned small integer, default 0
- `estimated_charge_min_nano` and `estimated_charge_max_nano` unsigned big integers; nullable `rates` JSON
- nullable unsigned integer `input_text_tokens`, `input_image_tokens`, `output_tokens`, and `reasoning_tokens`
- nullable unsigned big integer `provider_cost_nano` and `charge_nano`
- nullable `image_url` text; nullable `image_url_expires_at` timestamp
- nullable `error_code` string and `error_message` text; nullable `started_at` and `completed_at` timestamps; timestamps
- indexes: `(user_id, created_at)`, `(status, created_at)`, and `(completed_at)`

`webhook_events` columns:

- auto-increment `id`; unique `event_id`; `type`; nullable indexed `provider_generation_id`
- nullable unsigned small integer `delivery_attempt`; `payload` JSON; nullable `outcome`; timestamps

`App\Models\Generation` uses `HasUuids` and `HasFactory`, has `$guarded = []`, casts `status` to `GenerationStatus`, `reference_images` and `rates` to arrays, and `image_url_expires_at`, `started_at`, and `completed_at` to datetimes. It belongs to `User`.

`Database\Factories\GenerationFactory` provides `queued()`, `accepted()` (with `provider_id`), `succeeded()`, and `failed()` states.

`App\Models\WebhookEvent` has `$guarded = []` and casts `payload` to an array. `App\Models\User` has a `generations(): HasMany` relationship.

## Deferred implementation skeletons

The following methods currently throw `LogicException('Implemented in a later phase')`:

```php
App\Support\Money::rateToNanoPerToken(string $usdPerMillion): int
App\Support\Money::usdToNano(string $usd): int
App\Support\Money::format(int $nano): string

App\Services\CostCalculator::providerCost(array $usage, array $rates): int
App\Services\CostCalculator::charge(int $providerCostNano): int
App\Services\CostCalculator::estimate(array $pricing, int $promptChars, string $size, string $quality, int $referenceCount): array

App\Services\GenerationSettler::apply(array $snapshot): string
```

`CostCalculator::estimate()` will return `['min' => int, 'max' => int]` provider-cost nanodollars; callers apply the charge. `GenerationSettler::apply()` accepts a Pixelforge generation object and will return `applied`, `stale`, `final`, or `unknown_generation`.

`App\Jobs\SubmitGeneration implements ShouldQueue` and has `__construct(public Generation $generation)`; its `handle(): void` is deferred. `App\Console\Commands\ReconcileGenerations` has signature `generations:reconcile` and an empty success handle. `App\Http\Controllers\PixelforgeWebhookController` is invokable and currently responds with 204.

`App\Http\Controllers\GenerationController` exposes `index`, `create`, `estimate`, and `store`. The index/create actions render stub Inertia pages, estimate returns empty JSON until pricing mathematics is implemented, and store redirects to `generations.index`.

## Pixelforge client

`App\Services\Pixelforge` is a thin Http facade wrapper. Every request uses the configured base URL and bearer key, accepts JSON, has a 5-second connect timeout, and a 15-second response timeout.

- `create(Generation $generation): Illuminate\Http\Client\Response` posts `/generations` with `prompt`, `size`, `quality`, `metadata.local_id`, and `reference_images` only when non-empty. It sends `Idempotency-Key` equal to the generation UUID.
- `find(string $providerId): Response` gets `/generations/{id}`.
- `usage(string $startDate, string $endDate): Response` gets `/usage` with `start_date` and `end_date`.
- `pricing(): array` gets `/pricing`, calls `throw()->json()`, and caches the result under `pixelforge.pricing` for 600 seconds.

The `create`, `find`, and `usage` methods return response objects without throwing on 4xx or 5xx responses.

## Routes and scheduling

Authenticated routes:

- `GET /generations` → `GenerationController@index` (`generations.index`)
- `GET /generations/create` → `GenerationController@create` (`generations.create`)
- `GET /generations/estimate` → `GenerationController@estimate` (`generations.estimate`)
- `POST /generations` → `GenerationController@store` (`generations.store`)

`POST /webhooks/pixelforge` invokes `PixelforgeWebhookController` outside the auth group and is named `webhooks.pixelforge`. It is excluded from CSRF protection with Laravel 12's `validateCsrfTokens(except: ['webhooks/pixelforge'])` configuration. `/dashboard` redirects to `/generations` for authenticated users.

`routes/console.php` schedules `generations:reconcile` every minute with `withoutOverlapping()`.

## Inertia contracts

`resources/js/pages/generations/Create.vue` and `Index.vue` are minimal stubs rendered inside `AppLayout`. Create receives:

```ts
sizes: string[]
qualities: string[]
maxPromptLength: 4000
maxReferenceImages: 4
```

The future estimate endpoint contract is `GET /generations/estimate?chars=<int 0-4000>&size=&quality=&references=<int 0-4>` returning `{ min_nano: int, max_nano: int }`, user charge including markup.

Index receives a Laravel paginator with 20-per-page `data` items:

```ts
{
  id: string
  prompt: string
  size: string
  quality: string
  reference_images: string[]
  status: string
  status_note: string | null
  estimated_charge_min_nano: number
  estimated_charge_max_nano: number
  charge_nano: number | null
  charge_exact: string | null
  image_url: string | null
  image_expired: boolean
  error_message: string | null
  created_at: string
  completed_at: string | null
}
```

The app sidebar has `Generate` and `History` links.
