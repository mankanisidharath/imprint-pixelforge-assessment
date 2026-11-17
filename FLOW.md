# Flow — execution traceability

## Currently touching

Bootstrap, living documentation, and initial Git history. The Laravel 13 dependency upgrade is blocked by Composer security advisories.

## Reference flows

Generation creation: `GET /generations/create` -> `GenerationController::create` -> `resources/js/pages/generations/Create.vue`.

Generation history: `GET /generations` -> `GenerationController::index` -> `Generation` paginator -> `resources/js/pages/generations/Index.vue`.

Provider submission: future `SubmitGeneration` job -> `Pixelforge::create` -> Pixelforge `POST /generations`.

Webhook receipt: `POST /webhooks/pixelforge` -> `PixelforgeWebhookController` -> future signature verification and `GenerationSettler::apply`.
