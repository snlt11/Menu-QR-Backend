# Menu QR — Postman Collections

Three collections + one environment, organised to mirror `app/Http/Controllers/Api/`:

| File | Maps to |
|---|---|
| [Central.postman_collection.json](Central.postman_collection.json) | `Api/Central/*` — register, resolve tenant, tenant-first login |
| [Tenant.postman_collection.json](Tenant.postman_collection.json) | `Api/Tenant/*` — shop-profile, kitchen, cashier, reports |
| [Customer.postman_collection.json](Customer.postman_collection.json) | `Api/Customer/*` — menu, orders, payments |
| [menu-qr.postman_environment.json](menu-qr.postman_environment.json) | shared variables (`base_url`, `tenant_slug`, `qr_token`, tokens, captured IDs) |

## How to import
1. Postman → Import → drop all four JSON files in.
2. Select the `Menu QR - Local` environment in the top-right.
3. Run requests in this order to exercise the full happy path:

```
Central / Register                       (optional — owner already seeded by tenant:create)
Central / Tenant-First Login             → sets {{tenant_token}}
Customer / View Menu by Table QR         → sets {{menu_item_id}}
Customer / Create Order (Guest)          → sets {{order_id}}
Customer / Create Payment Session (QR)   → sets {{payment_session_id}}
Customer / Confirm Demo Payment          → order becomes paid + completed
Tenant   / Reports / Dashboard Overview  → sales reflects the order
```

## Variable capture
Each request that produces a new ID has a test script that writes it back to the environment so the next request can use it. No manual copy/paste required.

## Local setup recap
Run from `menu-qr-backend/` before hitting these requests:

```bash
docker compose up -d
php artisan migrate:fresh
php artisan tinker --execute='App\Models\User::create(["name"=>"Ko Aung","email"=>"koaung@example.com","password"=>bcrypt("password"),"global_role"=>"shop_owner"]);'
php artisan tenant:create "Shwe Food House" koaung@example.com --slug=shophouse
php artisan serve
```

Then seed at least one menu item + table in the tenant DB (or call the Customer / View Menu route after seeding via tinker).
