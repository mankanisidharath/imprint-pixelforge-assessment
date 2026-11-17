# imprint-pixelforge-assessment

An assessment project focused on correct handling of Pixelforge image-generation webhooks and integer nanodollar money values.

## Target stack

- Laravel 13
- Inertia 3 with Vue 3 and TypeScript
- Tailwind CSS and shadcn-vue
- Laravel Fortify authentication
- Pest
- PostgreSQL

The checked-in dependency lock currently remains on Laravel 12 and Inertia 2 because Composer blocks every available Laravel 13 release for active security advisories. Do not disable Composer's security blocking to force this upgrade. Upgrade when a secure Laravel 13 release and Laravel-13-compatible Pest dependencies are available.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=imprint_pixelforge_assessment
DB_USERNAME=postgres
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate
composer run dev
```

## Verification

```bash
php artisan test --compact
vendor/bin/pint --format agent
npm run build
```

## Documentation

- [Session context](CONTEXT.md)
- [Architecture](ARCHITECTURE.md)
- [Decisions](DECISIONS.md)
- [Execution flows](FLOW.md)
- [Pixelforge provider reference](docs/PIXELFORGE_API.md)
- [Foundation contracts](docs/CONTRACTS.md)

## Deployment

The intended host is Laravel Cloud. Configure environments and secrets in Laravel Cloud; never commit production credentials.
