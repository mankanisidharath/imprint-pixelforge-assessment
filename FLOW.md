# Flow — execution traceability

## Currently touching

Completed integration of the session's money, settlement, shared-code refactor, and billing documentation into local `main` by fast-forward. Verification passed all 80 tests, Pint, targeted ESLint checks, and the frontend build. Remote publication remains pending.

## Reference flows

Generation creation: `GET /generations/create` -> `GenerationController::create` -> `resources/js/pages/generations/Create.vue`.

`GenerationOptions` supplies the options for `GenerationController::create` and the defaults for `GenerationFactory`.

`TokenType::BILLABLE_KEYS` supplies the keys for `CostCalculator::providerCost` and `GenerationSettler::apply`. Estimates use the same individual token constants.

The planned estimate endpoint passes pricing, prompt character count, size, quality, and reference count to `CostCalculator::estimate`, then applies `charge` to both provider-cost bounds for the preview. The current endpoint returns empty JSON.

On successful completion, `GenerationSettler::apply` calculates provider cost from actual usage and snapshot rates, applies `charge`, and saves `provider_cost_nano` and `charge_nano`. Provider failure records zero for both amounts. Balance debiting remains a future phase.

Generation history: `GET /generations` -> `GenerationController::index` -> `Generation` paginator -> `resources/js/pages/generations/Index.vue`.

Each public `Pixelforge` method creates a fresh configured request through `request()` and sends it to the corresponding provider endpoint.

The future `SubmitGeneration` job will call `Pixelforge::create`, which posts to `/generations` with the local generation UUID as its idempotency key.

`POST /webhooks/pixelforge` verifies the signature, inserts the event once, and calls `GenerationSettler::apply` to update the locked generation.

The scheduled `generations:reconcile` command calls `Pixelforge::find` and sends the snapshot to `GenerationSettler::apply`.

Shared navigation definitions supply links for the header, sidebar, and generation breadcrumbs.
