# Menu QR — Implementation Plan

Source: [../menu_qr_full_version_stack.md](../menu_qr_full_version_stack.md)
Plan and infra live inside this backend folder; Laravel 13.8 already scaffolded here.

## Current State

- PHP 8.5.4 (stack doc recommends 8.4 — 8.5 works with Laravel 13)
- Composer 2.9.5 ✓
- Docker 28.0.4 + Compose v5.1.3 ✓
- Laravel 13.8 ✓
- Pint, Pest, Pail, Boost installed
- Sanctum ✗
- stancl/tenancy ✗
- league/flysystem-aws-s3-v3 ✗
- No docker-compose.yml
- `.env` points to local MySQL 3306; not yet wired to MinIO/Redis/tenancy

## Steps

### 1. Docker infrastructure
- Create `docker-compose.yml` at repo root (MySQL 8.4, Redis 8.4-alpine, MinIO pinned).
- `docker compose up -d`.
- Verify each service is reachable.

### 2. Backend composer packages
- `composer require laravel/sanctum`
- `composer require stancl/tenancy`
- `composer require league/flysystem-aws-s3-v3`

### 3. Update `.env`
- Switch `DB_PORT` to `3307`, `DB_DATABASE=menu_qr_central`, `DB_USERNAME=menu_qr_user`, `DB_PASSWORD=secret`.
- Set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`.
- Set `FILESYSTEM_DISK=s3` + AWS_* for MinIO.
- Add `SANCTUM_STATEFUL_DOMAINS`.
- Mirror to `.env.example`.

### 4. Sanctum setup
- `php artisan install:api` (publishes Sanctum config + migrations + `routes/api.php`).
- Add `HasApiTokens` to `User` model.

### 5. Tenancy setup
- `php artisan tenancy:install` (publishes config, migrations, service provider).
- Configure central connection `central` and tenant connection template in `config/database.php`.
- Set `tenancy.database.prefix` to `tenant_`.
- Register tenancy service provider in `bootstrap/providers.php`.

### 6. MinIO bucket
- Create `menu-qr` bucket via MinIO console at http://127.0.0.1:9001.
- Verify with `php artisan tinker` Storage put/get.

### 7. Run migrations
- `php artisan migrate` against central DB.
- Create a test tenant to verify tenant DB provisioning works.

### 8. Frontend (deferred)
- Not started in this pass. Plan: `npx create-next-app@latest menu-qr-web` per stack doc when backend foundations land.

## Confirmation Gates

I'll pause before:
- Step 2 (composer installs — modifies composer.lock)
- Step 3 (overwriting current `.env`)
- Step 5 (tenancy install — large publish + DB shape change)
