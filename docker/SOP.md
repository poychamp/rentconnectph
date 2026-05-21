# Local Docker SOP

This project runs locally with Docker Compose using PHP 8.2, Node 22, MySQL 8.0, and Mailpit.

## Files

- `compose.yaml` defines the local services.
- `docker/config/php/Dockerfile` builds the Laravel PHP image.
- `docker/config/node/Dockerfile` builds the Vite/Node image.
- `vendor/` is stored in a Docker named volume.
- `node_modules/` is stored in a Docker named volume.
- MySQL data is stored in a Docker named volume.

## Prerequisites

- Docker and Docker Compose plugin installed.
- A local `.env` file exists in the project root.
- Add this host entry on the host machine:

```text
127.0.0.1 rentconnectph.test
```

The expected local app URL is:

```text
APP_URL=http://rentconnectph.test
```

## Start The Stack

From the project root:

```bash
docker compose up
```

To run it in the background:

```bash
docker compose up -d
```

Open:

- App: `http://localhost`
- App domain: `http://rentconnectph.test`
- Vite dev server: `http://rentconnectph.test:5173`
- Mailpit: `http://localhost:8025`

## Web Server

This local setup intentionally uses Laravel's built-in development server instead of Nginx.

The container runs Laravel on port `8000`, and Docker exposes it on host port `80`:

```text
80:8000
```

That keeps the local URL aligned with:

```env
APP_URL=http://rentconnectph.test
```

Host port `80` must be free. If `http://rentconnectph.test` or `http://localhost` shows a system Apache/Nginx page instead of this Laravel app, stop the host webserver or temporarily change the app port mapping in `compose.yaml`.

Add Nginx only if local development needs to match production webserver behavior, such as custom rewrite rules, static file behavior, proxy headers, or TLS termination.

## First-Time App Setup

Run migrations and seeders:

```bash
docker compose exec app php artisan migrate --seed
```

Create or refresh the storage symlink:

```bash
docker compose exec app php artisan storage:link --force
```

Clear cached Laravel config after `.env` changes:

```bash
docker compose exec app php artisan optimize:clear
```

## Mailpit

Local mail should use Mailpit:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Keep live SMTP settings commented out in `.env` for local development.

Mailpit inbox:

```text
http://localhost:8025
```

## Vite Dev Server

`vite.config.js` reads explicit Vite dev server values from `.env`:

```env
VITE_DEV_SERVER_HOST=rentconnectph.test
VITE_DEV_SERVER_PORT=5173
VITE_DEV_SERVER_PROTOCOL=http
```

These values control the Vite dev server origin and hot reload host. Keep them aligned with the local hostname used in `APP_URL`.

## Common Commands

Run an Artisan command:

```bash
docker compose exec app php artisan <command>
```

Run Composer:

```bash
docker compose exec app composer <command>
```

Run npm:

```bash
docker compose exec node npm <command>
```

Open a shell in the PHP container:

```bash
docker compose exec app bash
```

Open a MySQL shell:

```bash
docker compose exec mysql mysql -uroot -p
```

## Rebuild Images

Rebuild after Dockerfile changes:

```bash
docker compose build
```

Rebuild one service:

```bash
docker compose build app
docker compose build node
```

## Reset Local Containers

Stop containers:

```bash
docker compose down
```

Stop containers and remove named volumes:

```bash
docker compose down -v
```

Use `down -v` only when you want to delete local MySQL data, `vendor/`, and `node_modules/` volumes.

## Dependency Volumes

Because `vendor/` and `node_modules/` are named Docker volumes, changes to `composer.lock` or `package-lock.json` may require reinstalling dependencies inside Docker.

Composer:

```bash
docker compose exec app composer install
```

npm:

```bash
docker compose exec node npm ci
```

To fully refresh dependency volumes:

```bash
docker compose down -v
docker compose up --build
```

This also removes the local MySQL data volume.
