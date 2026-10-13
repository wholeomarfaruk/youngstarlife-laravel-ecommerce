# YoungStar Ops — Laravel admin export

The admin panel from the design, rebuilt for **Laravel 11 · Livewire ^3.7 · Alpine (bundled with Livewire) · Tailwind ^3.4.13**.
Every page is a full-page Livewire component; layout, partials and UI pieces are Blade.

## Install
```bash
composer require livewire/livewire:^3.7 blade-ui-kit/blade-heroicons
npm i -D tailwindcss@^3.4.13 postcss autoprefixer
# copy this folder over your project, then:
# composer.json → "autoload": { "files": ["app/helpers.php"] }  && composer dump-autoload
php artisan migrate && php artisan db:seed --class=AdminSeeder
npm run build
```
Register routes in `bootstrap/app.php`:
```php
->withRouting(web: __DIR__.'/../routes/web.php', then: function () {
    Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
    Route::middleware('api')->group(base_path('routes/webhooks.php'));
})
```
Add `role` and `warehouse()` relation to `App\Models\User` (`belongsTo(Warehouse::class)`) and `use Notifiable` for the bell.

## Structure
```
config/admin.php                    nav, module tabs, every status → label + badge tone (single source)
tailwind.config.js                  design tokens: canvas #F6F5F1, ink #1A1A17, brand #1F5F4A, tones, Geist
resources/css/app.css               .btn-*, .input, .card, .th/.td, .badge-*, .chip, .mtab, .kpi
resources/js/app.js                 Alpine: $store.ui (drawer), menu, modal, toasts, scanner
resources/views/layouts/admin.blade.php
resources/views/partials/admin/     sidebar · topbar · bottom-nav (phones) · toasts
resources/views/components/admin/   module-tabs page-header kpi kpis badge card chips toolbar search date-range
                                    bulk-bar th table empty row-menu menu-item modal field pagination steps
app/Livewire/Admin/                 Dashboard · GlobalSearch · NotificationBell · Concerns/WithTable
  Products/{Index,Edit}  Batches/Index  Purchases/{Index,Edit}  SupplierReturns/Index
  Orders/{Index,Show,ByDate,Campaigns,Drafts,OrderedProducts,InCourier,RowActions}
  Packing/Scan  Returns/Receive  Couriers/Index  Customers/Index  Suppliers/Index
  Accounts/Index  Reports/Index  Storefront/Index  Settings/Index
app/Services/                       StockService (all stock changes + ledger) · BatchAllocator (FIFO, cost at pack) · ReceivingService (PO → batches)
app/Models/                         30 models · database/migrations (5 files, 30 tables)
```

## Core flows (same as the design)
- **Supplier → PO → Receive (partial OK) → one batch per variant → labels** (`Purchases/Edit`, `ReceivingService`, `/admin/labels/print`). Stock only enters through batches.
- **Order → confirm (FIFO batch reservation) → Packing scan → packed** (`BatchAllocator::pack` deducts batches and stores the purchase price on each allocation).
- **Courier webhook → courier_status** (`routes/webhooks.php`) → order status.
- **Return receive** (scan parcel → scan items → reason + condition) → good restocks the *original batch*, damaged goes to damaged stock, missing = courier claim; refunds post to Accounts.
- **Supplier returns**: damaged items grouped by the batch's supplier → Pending → Sent → Received → Approved → Resolved (replacement / credit / refund).

## Why it's fast
- `wire:navigate` on every link (SPA-like, no full reloads) and `@persist`-free layout.
- Each page selects only needed columns, eager-loads with column lists, and paginates (25).
- Denormalised counters: `product_variants.stock/reserved/damaged`, `customers.orders_count/delivered_count`, `suppliers.due` → list pages never aggregate batches.
- Composite indexes for the hot paths: FIFO lookup, orders by status/date, drafts.
- Dashboard widgets are single grouped queries cached 60 s; dropdown data (categories, zones) cached.
- `#[Computed]` + `#[Url]` state, `wire:model.live.debounce` on search, lazy `RowActions`, bell polls only when the tab is visible.
- No extra JS libs; Alpine only for menus, modals, drawer, toasts and the scanner input.

Production: `php artisan optimize`, `php artisan view:cache`, OPcache on, Redis for cache/session/queue.
