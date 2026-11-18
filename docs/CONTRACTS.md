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

## Shared Pixelforge definitions

`App\Features\Pixelforge\TokenType` defines `INPUT_TEXT`, `INPUT_IMAGE`, `OUTPUT`, and `REASONING` as the provider's token field names. `BILLABLE_KEYS` contains these four constants. `CostCalculator` and `GenerationSettler` use this list for charging and storing usage. Tests exercise the literal provider keys independently.

`App\Features\Pixelforge\GenerationOptions` defines `SIZES`, `QUALITIES`, `DEFAULT_SIZE`, `DEFAULT_QUALITY`, `MAX_PROMPT_LENGTH`, and `MAX_REFERENCE_IMAGES`. The generation controller and factory share these definitions.

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

## Money and settlement services

The service contracts retain these signatures.

```php
App\Support\Money::rateToNanoPerToken(string $usdPerMillion): int
App\Support\Money::usdToNano(string $usd): int
App\Support\Money::format(int $nano): string

App\Services\CostCalculator::providerCost(array $usage, array $rates): int
App\Services\CostCalculator::charge(int $providerCostNano): int
App\Services\CostCalculator::estimate(array $pricing, int $promptChars, string $size, string $quality, int $referenceCount): array

App\Services\GenerationSettler::apply(array $snapshot): string
```

`Money` parses non-negative decimal strings with exact precision checks and formats integer nanodollars with nine decimal places. `CostCalculator::estimate()` returns `['min' => int, 'max' => int]` provider-cost nanodollars. Callers apply the charge. `GenerationSettler::apply()` accepts a Pixelforge generation object and returns `applied`, `stale`, `final`, or `unknown_generation`.

### Money units and conversions

One USD equals 1,000,000,000 nanodollars. `rateToNanoPerToken` parses USD-per-million rates with three implied decimal places, so `'12.50'` becomes 12,500 nano per token. `usdToNano` uses nine implied decimal places, so `'0.169080000'` becomes 169,080,000 nano. `format` produces a USD string with exactly nine decimal places.

Parsing accepts whole digits with an optional fractional part. Extra fractional digits are accepted when all digits beyond the scale are zero. Malformed strings, negative amounts, precision loss, and values above `PHP_INT_MAX` raise `InvalidArgumentException`.

### Markup and rounding

`providerCost` sums usage multiplied by the corresponding nano-per-token rate for each key in `TokenType::BILLABLE_KEYS`. `total_tokens` is an aggregate usage field and is excluded from the sum.

`charge` adds `services.pixelforge.markup_percent` percent to provider cost and rounds upward to a whole nanodollar. With the configured 25% markup, `charge(1)` returns 2, `charge(4)` returns 5, and `charge(101)` returns 127. The rounding unit is one nanodollar, or USD 0.000000001.

The implementation splits the cost into whole hundreds and a remainder to keep intermediate values within PHP's integer range. Each hundred contributes exactly the percentage in nano of markup. The fractional remainder uses integer division with an upward-rounding adjustment. This preserves integer arithmetic for large costs whose direct percentage multiplication could overflow. Negative costs and markups raise `InvalidArgumentException`. Results beyond the integer range raise `OverflowException`.

### Estimates and final recorded charges

Before completion, text tokens are approximate and reasoning tokens vary by quality. `estimate` rounds prompt characters up to a text-token count, applies the configured text variance with downward rounding for the minimum and upward rounding for the maximum, and uses the quality's reasoning minimum and maximum. Image and output token counts are fixed by the selected inputs. Both combinations are priced through `providerCost`.

The planned preview applies `charge` to each estimate bound. For 400 characters, high quality, size `1536x1024`, and two references, the provider-cost range is 286,225,000–373,875,000 nano. Applying 25% markup gives a preview range of 357,781,250–467,343,750 nano.

On success, settlement calculates the final recorded charge from actual reported usage and the generation's fixed rates. For example, actual usage of 100 text, 1,530 image, 6,450 output, and 3,000 reasoning tokens costs 311,300,000 nano at the documented rates and produces a charge of 389,125,000 nano. Failed generations record zero cost and charge.

Settlement persists these amounts in `provider_cost_nano` and `charge_nano`. Balance debiting remains a future phase. The current estimate endpoint, creation action, and submission job still require implementation.

`App\Jobs\SubmitGeneration implements ShouldQueue` and has `__construct(public Generation $generation)`. Its `handle(): void` is deferred. `App\Console\Commands\ReconcileGenerations` has signature `generations:reconcile` and polls old unfinished generations through the shared settler. `App\Http\Controllers\PixelforgeWebhookController` verifies signatures, records events once, and settles generation snapshots before responding with 204.

`App\Http\Controllers\GenerationController` exposes `index`, `create`, `estimate`, and `store`. The index/create actions render stub Inertia pages, estimate returns empty JSON until it is connected to the calculator, and store redirects to `generations.index`.

## Pixelforge client

`App\Services\Pixelforge` is a thin Http facade wrapper. A shared private `request()` method creates a fresh request for each call with the configured base URL and bearer key, JSON acceptance, a 5-second connect timeout, and a 15-second response timeout.

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
