# Flow — execution traceability

## Currently touching

Updated the existing webhook test to send a signed ping without a CSRF token. All 75 tests passed on isolated local PostgreSQL; merge the fix into local `main`.

## Reference flows

Generation creation: `GET /generations/create` -> `GenerationController::create` -> `resources/js/pages/generations/Create.vue`.

Generation history: `GET /generations` -> `GenerationController::index` -> `Generation` paginator -> `resources/js/pages/generations/Index.vue`.

Provider submission: future `SubmitGeneration` job -> `Pixelforge::create` -> Pixelforge `POST /generations`.

Webhook receipt: `POST /webhooks/pixelforge` -> `PixelforgeWebhookController` verifies the signature and records the event -> `GenerationSettler::apply` for generation events.
