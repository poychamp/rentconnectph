# RentConnectPH

**A rental listing platform for Cagayan de Oro, Philippines** — connecting renters directly with property owners.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-38BDF8?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)

🌐 **[rentconnectph.com](https://rentconnectph.com)**

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Vue 3 (Composition API), mounted per page via Vite |
| Styling | Tailwind CSS 4 |
| UI components | PrimeVue 4 (Aura preset, brand orange) |
| HTTP | Axios |
| Search | Laravel Scout (database driver locally, hosted search in production) |
| Auth (API) | Laravel Sanctum |
| Permissions | spatie/laravel-permission |
| Hosting | Serverless (AWS via Laravel Vapor) |

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ and npm

### Installation

```bash
# Install dependencies
composer install
npm install

# Set up your environment
cp .env.example .env
php artisan key:generate

# Configure your database in .env, then run migrations
php artisan migrate
php artisan db:seed
```

### Running locally

Run the Vite dev server (with hot module replacement) and the Laravel app:

```bash
# Terminal 1 — frontend assets
npm run dev

# Terminal 2 — application server
php artisan serve
```

The app will be available at the URL printed by `php artisan serve`.

## Available Commands

```bash
npm run dev      # Start the Vite dev server (HMR)
npm run build    # Build production frontend assets
php artisan test # Run the test suite
```

## Testing

The application is covered by feature tests built on PHPUnit:

```bash
php artisan test
```

Tests run against an isolated database and never touch development data.

## Deployment

The application is deployed to a serverless environment on AWS using Laravel Vapor.
Frontend assets are served from a CDN, and images are stored on S3.

## Security

If you discover a security vulnerability, please report it privately to
**info@rentconnectph.com** rather than opening a public issue. We take all reports
seriously and will respond as quickly as possible.

## License

© 2026 RentConnectPH. All rights reserved. This is proprietary software and is not licensed for redistribution.
