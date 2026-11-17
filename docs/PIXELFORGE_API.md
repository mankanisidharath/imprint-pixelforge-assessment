# Pixelforge API reference (facts from the provider docs)
Base URL: https://pixelforge.promptedit.dev/v1 . JSON. Header `Authorization: Bearer <key>`.
Timestamps ISO 8601 UTC. Ids are prefixed: `gen_` generations, `evt_` webhook events.
Money in API responses is a decimal string in USD. The API never returns a price per generation.

## Endpoints
- POST /v1/generations -> 202 with a generation object, status "queued".
  Body: prompt (string, required, max 4000 characters); size ("1024x1024" default, "1536x1024",
  "1024x1536"); quality ("low", "medium" default, "high"); reference_images (array of http/https
  URLs, max 4, each billed as 765 input image tokens); metadata (object, string keys and string
  values, max 20 keys, values up to 500 chars, echoed on the generation and in every webhook).
  Unknown parameters are rejected with 422 validation_failed.
  Optional header `Idempotency-Key` (up to 255 chars, account-scoped, never expires). Same key +
  same parameters returns the original generation with its CURRENT status, HTTP 202 and header
  `Idempotent-Replayed: true`; nothing new is created or billed. Same key + different parameters
  -> 409 idempotency_key_reused.
- GET /v1/generations/{id} -> generation object (404 not_found if not yours).
- GET /v1/generations -> list, newest first. Query: limit (1-100, default 20), status, cursor.
  Envelope: object "list", data, has_more, next_cursor.
- GET /v1/pricing -> no auth needed. See Pricing.
- GET /v1/usage -> query start_date, end_date (YYYY-MM-DD, UTC, inclusive; default last 30 days;
  max 92 days else 422 date_range_too_long). Response: object "usage", start_date, end_date,
  data[] of { date, generations, input_text_tokens, input_image_tokens, output_tokens,
  reasoning_tokens, cost_usd } and total { same fields without date }. cost_usd is a decimal
  string with nine decimals, exact and unrounded. A generation is counted on the UTC day it
  COMPLETED. Scope: generations created with the API key used for the call.

## Generation object
id, object ("generation"), status ("queued" | "processing" | "succeeded" | "failed"; the last
two are final), prompt, size, quality, reference_images, metadata (always an object),
image_url (null until succeeded; signed PNG URL that expires 24h after completion),
image_url_expires_at, usage (null until succeeded; then { input_text_tokens,
input_image_tokens, output_tokens, reasoning_tokens, total_tokens }), rates ({ input_text_tokens,
input_image_tokens, output_tokens, reasoning_tokens } as decimal strings, USD per 1M tokens,
FIXED at creation: this generation is billed at these rates), error (null unless failed; then
{ code: "generation_failed" | "internal_error", message }), created_at, started_at,
completed_at (set for succeeded and failed).
Failed generations are never billed and have usage null.

## Pricing (GET /v1/pricing)
{ object: "pricing", currency: "usd", unit: "per_1m_tokens",
  rates: { input_text_tokens: "5.00", input_image_tokens: "10.00", output_tokens: "40.00",
          reasoning_tokens: "12.50" },
  tokens: { characters_per_text_token: 4, text_token_variance: 0.15, per_reference_image: 765,
   output: { low: {"1024x1024": 280, "1536x1024": 420, "1024x1536": 420},
             medium: {"1024x1024": 1100, "1536x1024": 1650, "1024x1536": 1650},
             high: {"1024x1024": 4300, "1536x1024": 6450, "1024x1536": 6450} },
   reasoning: { low: {min: 0, max: 500}, medium: {min: 200, max: 2000},
                high: {min: 1000, max: 8000} } } }
Text tokens are estimated as ceil(characters / 4) (Unicode characters, not bytes); the real count
is within 15% of that in either direction and only known on completion. Reasoning tokens are only
known on completion, within the range for the quality. Image and output tokens are exact.
Cost = sum over the four token types of tokens * rate / 1,000,000. Never rounded.

## Errors
Shape: { "error": { "type", "code", "message", "param"?, "errors"? } }. Branch on `code`.
invalid_api_key 401 | validation_failed 422 | content_policy_violation 400 (nothing created or
billed) | not_found 404 | idempotency_key_reused 409 | rate_limit_exceeded 429 (wait the seconds
in the `Retry-After` header) | concurrency_limit_exceeded 429 (`Retry-After: 5`) |
daily_limit_exceeded 429 (no Retry-After documented) | overloaded 503 (`Retry-After: 2`, nothing
created) | date_range_too_long 422.
Guidance: respect Retry-After, back off further after each attempt, stop after a few attempts;
every retry counts towards the per-minute limit.
Limits (per account, across keys): 60 requests/minute; 3 concurrent generations (queued or
processing); 500 generations per UTC day. Rejected requests create nothing and cost nothing.

## Webhooks
POST to our URL with headers: Pixelforge-Signature, Pixelforge-Event-Id, Pixelforge-Event-Type,
Pixelforge-Delivery-Attempt. Body: { id: "evt_...", object: "event", type, created_at,
data: { object: <full generation object as it was when the event happened> } }.
Types: generation.processing, generation.succeeded, generation.failed, ping (test event).
Signature header format: `t=<unix timestamp>,v1=<hex>`. Signed string: "<t>.<raw request body>".
Algorithm: HMAC-SHA256, hex, keyed with the signing secret (starts with whsec_). Verify against
the RAW body before JSON decoding, compare in constant time, reject timestamps more than 300
seconds from now. Respond with any 2xx within 10 seconds; anything else is a failed delivery.
Retries after 10s, 30s, 2m, 10m, 15m (6 attempts total), then nothing.
Delivery is at-least-once: duplicates and manual resends keep the SAME event id. Events can
arrive out of order (e.g. a retried generation.processing after generation.succeeded). There is
NO sequence field: use the generation status in the payload and never move a generation out of
succeeded/failed. A webhook can arrive BEFORE our POST /v1/generations call has returned.
Delivery is not guaranteed: fall back to polling GET /v1/generations/{id}.

## Test keywords (anywhere in the prompt, case-insensitive)
[blocked] -> 400 content_policy_violation at submit. [overloaded] -> 503 overloaded at submit.
[fail] -> accepted, then fails during processing. [succeed] -> never fails at random.
[slow] -> processing takes 60-90s instead of 5-30s. [duplicate] -> every webhook sent twice.
