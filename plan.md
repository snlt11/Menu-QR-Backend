# Menu QR — Implementation Plan

Sources:
- Stack: [../menu_qr_full_version_stack.md](../menu_qr_full_version_stack.md)
- Product design: `~/Downloads/menu_qr_final_product_design.md`

**Hard rule:** All primary keys and foreign keys use UUID v7 (`Str::uuid()` via `HasUuids`). No bigint IDs anywhere.

## Project Scope (from design doc)

Multi-tenant QR menu / ordering / payment / loyalty platform.
- Central DB (`menu_qr_central`) — global users, tenants, domains.
- One tenant DB per shop (`tenant_<slug>`) — shop profile, staff, tables, menu, orders, payments, loyalty.
- Path-based tenancy: `/t/{slug}/...` for staff, `/s/{slug}/table/{qr_token}/...` for customers.
- Roles: super_admin, owner, manager, cashier, kitchen, member, guest.
- Two payment timings: pay_before_prepare, pay_after_meal.
- Payment provider: `demo_qr` for level 5.
- Loyalty: per-tenant points (earn after payment, redeem during checkout).

## What's Already Done

- [x] Docker stack up: mysql:8.4 (3307), redis:8.4-alpine (6379), minio (9000/9001).
- [x] Composer packages: laravel/sanctum ^4.3, stancl/tenancy ^3.10, league/flysystem-aws-s3-v3 ^3.34.
- [x] `.env` + `.env.example`: central DB, redis cache/queue, MinIO S3, Sanctum stateful domains.
- [x] `config/database.php`: `central` + `tenant` connections added; default = `central`.
- [x] `php artisan install:api` — Sanctum scaffold + default migrations ran on central DB.
  - **Caveat:** default `users`, `cache`, `jobs`, `personal_access_tokens` migrations used bigint IDs. Must roll back and rewrite as UUID before continuing.

## Remaining Steps

### Phase A — Foundation rework (UUIDs)

1. **Roll back default migrations** on central DB:
   ```
   php artisan migrate:rollback
   ```
2. **Rewrite default migrations to UUID:**
   - `0001_01_01_000000_create_users_table.php` — `$table->uuid('id')->primary()`. Add `phone`, `global_role`, `status` per design §4.2. Drop `password_reset_tokens` (will replace as `password_resets` with UUID FK).
   - `0001_01_01_000001_create_cache_table.php` — keys are strings, no change needed.
   - `0001_01_01_000002_create_jobs_table.php` — keep bigint for job IDs (internal queue table, not a domain entity).
   - `2026_05_17_064807_create_personal_access_tokens_table.php` — change `morphs('tokenable')` to `uuidMorphs('tokenable')`.
3. **User model:** add `HasUuids` + `HasApiTokens` traits; `$keyType = 'string'`, `$incrementing = false`; cast `id` as string.

### Phase B — Tenancy install

4. `php artisan tenancy:install` — publishes config, migrations, provider.
5. **Tenant model:** create `App\Models\Tenant` extending `Stancl\Tenancy\Database\Models\Tenant`; use UUID PKs; add `slug`, `database_name`, `owner_user_id`, `status` (design §4.3).
6. **Tenant Domain model:** extend stancl's `Domain` with UUID PK.
7. **Config `tenancy.php`:**
   - `tenant_model` → `App\Models\Tenant`
   - `domain_model` → `App\Models\TenantDomain`
   - `database.prefix` → `tenant_`
   - `database.template_tenant_connection` → `tenant`
   - `central_domains` → `[]` (path-based, not domain-based)
   - Bootstrappers: `DatabaseTenancyBootstrapper`, `CacheTenancyBootstrapper`, `FilesystemTenancyBootstrapper`, `QueueTenancyBootstrapper`.
8. **Rewrite tenancy's create_tenants/create_domains migrations** to UUID columns.
9. **Identification:** use `InitializeTenancyByPath` middleware on `/t/{tenant}/...` and `/s/{tenant}/...` route groups; tenant param resolved by `slug`.
10. Register tenancy service provider in `bootstrap/providers.php`.

### Phase C — Central schema (UUID migrations)

11. `tenants` — id, owner_user_id (FK central.users.id), name, slug (unique), database_name (unique), status, timestamps.
12. `tenant_domains` — id, tenant_id, domain, type, is_primary, timestamps.
13. `tenant_user_access` — id, tenant_id, user_id, role_in_tenant, status, timestamps (lets a central user belong to many tenants).
14. `password_resets` — id, user_id, token, created_at.
15. Run `php artisan migrate` on central.

### Phase D — Tenant schema (UUID migrations, in `database/migrations/tenant/`)

Tenant-only migrations live in `database/migrations/tenant/` and run via `tenants:migrate`.

16. `shop_profile` (single-row) — design §9.1.
17. `shop_settings` (single-row) — design §9.2.
18. `shop_users` — design §10.1, `central_user_id` references central but stored as UUID string only.
19. `shop_tables` — design §11.1, includes `qr_token` (unique).
20. `menu_categories` — design §12.1.
21. `menu_items` — design §13.1, FK `menu_category_id`.
22. `menu_collections` — design §14.4 (`layout_type` enum: horizontal_cards / grid_cards / large_featured_cards / compact_list).
23. `menu_collection_items` — pivot, design §14.5.
24. `customers` + `customer_profiles` — design §24.1.
25. `orders` — design §19.1 (all amount fields, payment_status, status, redeemed/earned points).
26. `order_items` — design §19.2 with `snapshot_name` and `snapshot_price`.
27. `payments` — design §22.1.
28. `payment_sessions` — design §22.2 (qr_payload, provider_reference, expires_at).
29. `payment_status_histories` — design §22.3.
30. `loyalty_point_transactions` — design §24.2 (type: earn/redeem/refund/adjustment).

### Phase E — MinIO bucket + storage smoke test

31. Create `menu-qr` bucket via mc/minio console at http://127.0.0.1:9001.
32. `Storage::disk('s3')->put('healthcheck.txt', 'ok')` via tinker; verify URL.

### Phase F — Tenant provisioning workflow

33. `App\Actions\CreateTenant` — accepts owner user + name + slug, creates `tenants` row, runs `tenants:migrate`, seeds default `shop_profile` + `shop_settings`, inserts owner into `shop_users`.
34. Artisan command `tenant:create {slug} {owner_email}` for demo seeding.
35. Smoke test: create `shophouse` tenant; verify `tenant_shophouse` DB exists + has all tables.

### Phase G — Auth and tenant-first login

36. Central endpoints (`routes/api.php`):
    - `POST /api/register` — central user signup.
    - `GET /api/tenants/resolve?slug=` — returns tenant existence.
    - `POST /api/t/{slug}/login` — verify central user, switch to tenant, check `shop_users` role + status, issue Sanctum token, return `redirect_url` per role.
37. Token abilities = `shop_user.role` value so policies/middleware can check.

### Phase H — Tenant staff routes (skeleton)

38. Route group `/api/t/{tenant}` with `InitializeTenancyByPath` + `auth:sanctum`:
    - shop-profile (GET/PUT)
    - shop-settings (GET/PUT)
    - staff CRUD
    - tables CRUD (+ generates `qr_token`)
    - menu-categories CRUD
    - menu-items CRUD
    - menu-collections CRUD + collection items
    - orders index/show
    - payments index
    - reports (basic counts)

### Phase I — Customer public routes

39. Route group `/api/s/{tenant}` with `InitializeTenancyByPath` (no auth required for menu/order; auth optional for member features):
    - `GET /table/{qr_token}/menu` — shop profile + active collections + categories + items.
    - `POST /table/{qr_token}/orders` — validate items from tenant DB, snapshot prices, compute totals, create order + order_items.
    - `POST /orders/{order}/apply-points` — only for authed member.
    - `POST /orders/{order}/payments` — create payment + payment_session (demo_qr) with qr_payload + expires_at.
    - `GET /orders/{order}/status` — polling endpoint.

### Phase J — Payment simulation + status workflow

40. `App\Services\DemoQrProvider` — generates qr_payload string + provider_reference.
41. Webhook/simulator endpoint `POST /api/_demo/payments/{session}/confirm` — flips status to `paid`.
42. `App\Listeners\HandlePaymentSucceeded`:
    - inside tenant context: lock order, idempotency check, mark `payments.status=paid`, mark `orders.payment_status=paid`, write `payment_status_histories`.
    - if order has redeemed_points: insert `loyalty_point_transactions` redeem row, decrement `customer_profiles.total_points`.
    - if member + points_enabled: compute earned = floor(payable_amount / earn_rate_amount) × earn_rate_points, insert earn row, increment points.
    - advance order status based on payment_timing.

### Phase K — Kitchen + cashier views

43. Kitchen routes (status transitions: accepted → preparing → ready → served).
44. Cashier routes (list unpaid, generate payment QR, confirm cash, close pay-after-meal bills).
45. Status visibility rule: kitchen sees order immediately for pay_after_meal; only after payment_status=paid for pay_before_prepare.

### Phase L — Reports

46. Endpoint: today sales, today orders count, unpaid bills count, popular items (group by snapshot menu_item_id), points redeemed today.

### Phase M — Tests

47. Pest feature tests per route group, all using `RefreshDatabase` on central and tenant. Cover:
    - tenant create + migrate
    - tenant-first login (happy, wrong tenant, inactive shop_user)
    - guest order create
    - member order create + apply points
    - demo_qr payment success → order paid + points adjusted
    - pay-before vs pay-after kitchen visibility
    - menu collection visibility window

### Phase N — Frontend (deferred)

48. Out of scope for this pass. Stack doc covers `npx create-next-app menu-qr-web` setup when API is functional.

## UUID Implementation Pattern

Every Eloquent model:
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Foo extends Model {
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
}
```

Every migration table:
```php
$table->uuid('id')->primary();
$table->foreignUuid('other_id')->constrained('others');
$table->uuidMorphs('subject'); // when polymorphic
$table->timestamps();
```

## Next Action

Begin Phase A — roll back the bigint migrations and rewrite them with UUIDs.
