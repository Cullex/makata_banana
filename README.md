# Makata Banana: Warehouse Management (demo)

Laravel 12 API + Vue 3 SPA for receiving, ripening and selling bananas.

## Run it

```bash
# MySQL (XAMPP) must be running; DB = makata_banana (see .env)
php artisan migrate:fresh --seed   # seeds 45 days of simulated operations
npm install && npm run build       # or `npm run dev` for hot reload
php artisan serve                  # http://127.0.0.1:8000
```

Demo logins (password `password`): `manager@`, `warehouse@`, `inspector@`, `sales@makata.test`.
The login screen has one-click buttons for each role.

## Structure

- `routes/web.php`: JSON API under `/api` (session auth + CSRF), role middleware, SPA catch-all
- `app/Services/Warehouse.php`: FIFO allocation, pricing/discounts, alerts, stock movements
- `app/Http/Controllers/Api/*`: dashboard, batches, rooms, inventory, orders, pricing, reports, users
- `database/seeders/DatabaseSeeder.php`: day-by-day simulation, so every number reconciles
- `resources/js/`: Vue app (`pages/` are lazy-loaded route chunks, `components/charts/` are dependency-free SVG charts)

## Notes

- Currency is set with `APP_CURRENCY` in `.env` (default `KES`).
- Room sensor values are simulated around each room's set point (`StorageLocation::liveReading`).
- Batches age automatically: ripe → overripe after 3 days, and stock with less than 1 day of shelf life is sold at a 50% clearance price.
