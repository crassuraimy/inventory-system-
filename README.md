# Inventory Management System Starter

Laravel API + React/Vite starter for one main store, medical and general inventory, quantity-only stock tracking, dated stock movements, opening stock, purchases, stock issues, monthly 1–31 register, and dashboard low-stock alerts.

## Backend setup
The backend is a Laravel API using Sanctum authentication and PostgreSQL/Supabase.

1. Install PHP dependencies:
   ```sh
   cd backend
   composer install
   ```
2. Copy `.env.example` to `.env` and update the database and Supabase values. For Supabase, use the Supabase PostgreSQL connection details and password only in `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=your-supabase-pooler-host
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.your-project-ref
   DB_PASSWORD=your-supabase-database-password
   DB_SSLMODE=require
   ```
3. Generate the application key and run migrations:
   ```sh
   php artisan key:generate
   php artisan migrate --seed
   ```
4. Start the API:
   ```sh
   php artisan serve
   ```

Do not commit `.env` or store database passwords in documentation. Use placeholders such as `YOUR_ACCESS_TOKEN` and `your-supabase-database-password` in examples.

Authentication is provided by `POST /api/v1/auth/login`. The frontend stores the returned Sanctum token in `sessionStorage` as `inventory_token`, sends it on protected requests, and clears it on logout or HTTP 401. Create users using `php artisan tinker`, `php artisan db:seed`, or your preferred provisioning workflow; do not hardcode access tokens.

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
