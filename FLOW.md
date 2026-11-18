# Flow — execution traceability

## Currently touching

Sidebar branding and navigation, including the local user guide.

## Reference flows

Generation creation: `GET /generations/create` -> `GenerationController::create` -> `resources/js/pages/generations/Create.vue`.

Generation history: `GET /generations` -> `GenerationController::index` -> `Generation` paginator -> `resources/js/pages/generations/Index.vue`.

Provider submission: future `SubmitGeneration` job -> `Pixelforge::create` -> Pixelforge `POST /generations`.

Webhook receipt: `POST /webhooks/pixelforge` -> `PixelforgeWebhookController` -> future signature verification and `GenerationSettler::apply`.

Sidebar documentation: `AppSidebar` -> `/user-guide.html` -> generation usage and current availability.
