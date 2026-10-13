# Inventory Management System Starter

Laravel API + React/Vite starter for one main store, medical and general inventory, quantity-only stock tracking, dated stock movements, opening stock, purchases, stock issues, monthly 1–31 register, and dashboard low-stock alerts.

## Backend setup
1. Create Laravel project: `composer create-project laravel/laravel inventory-api`
2. Copy the contents of `backend/` into the Laravel project (preserving paths).
3. Configure `.env` for MySQL:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventory
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Run `php artisan install:api` if `routes/api.php` does not exist.
5. Run `php artisan migrate --seed`
6. Start: `php artisan serve`

This starter uses Laravel Sanctum's `auth:sanctum` middleware for protected routes. Create a user using `php artisan tinker` or your preferred admin workflow. For quick local development only, routes can temporarily be made public, but do not deploy that way.

## Frontend setup
1. `cd frontend`
2. `npm install`
3. Copy `.env.example` to `.env` and set `VITE_API_URL=http://127.0.0.1:8000/api`
4. `npm run dev`

Sign-in/token management is intentionally left as a small integration step because the exact authentication UI depends on your user provisioning policy. API routes are protected by Sanctum. The frontend API helper supports a bearer token stored in sessionStorage under `inventory_token`.

## Business rules
- Quantities are stored as DECIMAL(14,3) to support pieces, packs, litres, and other units.
- Opening stock is entered once per item as a positive inbound movement.
- A purchase increases stock only when posted/received.
- A stock issue decreases stock only when posted.
- Duplicate posting is rejected.
- Stock cannot go negative.
- Posted movements should not be edited/deleted; reverse them through an authorized reversal workflow before production use.
- Low stock means `quantity_on_hand <= minimum_stock`.
- No unit costs or stock valuation are included.
- Monthly register returns every valid day in the selected month. The UI can show 31 column slots and disable non-existent dates.
- The sample posting service is a starter. Before production, add transaction authorization, reversal workflows, audit logging, rate limits, tests, and robust handling of concurrent requests.
