# Flow — execution traceability

## Currently touching

Verifying submission, webhook settlement, reconciliation, and generation pages together on phase-6-integration.

## Reference flows

Generation creation: `GET /generations/create` -> `GenerationController::create` -> `resources/js/pages/generations/Create.vue`.

`GenerationOptions` supplies the options for `GenerationController::create` and the defaults for `GenerationFactory`.

`TokenType::BILLABLE_KEYS` supplies the keys for `CostCalculator::providerCost` and `GenerationSettler::apply`. Estimates use the same individual token constants.

The estimate endpoint passes pricing, prompt character count, size, quality, and reference count to `CostCalculator::estimate`, then applies `charge` to both provider-cost bounds. Generation creation stores those bounds and dispatches submission.

On successful completion, `GenerationSettler::apply` calculates provider cost from actual usage and snapshot rates, applies `charge`, and saves `provider_cost_nano` and `charge_nano`. Provider failure records zero for both amounts. Balance debiting remains a future phase.

Generation history: `GET /generations` -> `GenerationController::index` -> `Generation` paginator -> `resources/js/pages/generations/Index.vue`.

Each public `Pixelforge` method creates a fresh configured request through `request()` and sends it to the corresponding provider endpoint.

`SubmitGeneration` calls `Pixelforge::create`, which posts to `/generations` with the local generation UUID as its idempotency key. Accepted snapshots go to the settler; retryable errors release the job, and rejection conditionally fails only unaccepted queued rows.

`POST /webhooks/pixelforge` verifies the signature, inserts the event once, and calls `GenerationSettler::apply` to update the locked generation.

The scheduled `generations:reconcile` command calls `Pixelforge::find` and sends the snapshot to `GenerationSettler::apply`.

Shared navigation definitions supply links for the header, sidebar, and generation breadcrumbs.

Sidebar documentation: `AppSidebar` -> `/user-guide.html` -> generation usage.
