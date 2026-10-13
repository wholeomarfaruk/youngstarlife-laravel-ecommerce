<?php
// bootstrap/app.php → ->withRouting(..., then: fn () => Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(base_path('routes/admin.php')))

use App\Http\Controllers\Admin\PrintController;
use App\Livewire\Admin as P;
use Illuminate\Support\Facades\Route;

Route::get('/', P\Dashboard::class)->name('dashboard');

// Product & Inventory
Route::get('products', P\Products\Index::class)->name('products.index');
Route::get('products/{product}', P\Products\Edit::class)->name('products.edit');
Route::get('batches', P\Batches\Index::class)->name('batches.index');
Route::get('purchases', P\Purchases\Index::class)->name('purchases.index');
Route::get('purchases/{po}', P\Purchases\Edit::class)->name('purchases.edit');
Route::get('supplier-returns', P\SupplierReturns\Index::class)->name('supplier-returns.index');

// Orders & Packing
Route::get('orders', P\Orders\Index::class)->name('orders.index');
Route::get('orders/by-date', P\Orders\ByDate::class)->name('orders.by-date');
Route::get('orders/campaigns', P\Orders\Campaigns::class)->name('orders.campaigns');
Route::get('orders/drafts', P\Orders\Drafts::class)->name('orders.drafts');
Route::get('orders/products', P\Orders\OrderedProducts::class)->name('orders.products');
Route::get('orders/in-courier', P\Orders\InCourier::class)->name('orders.in-courier');
Route::get('orders/print', [PrintController::class, 'invoices'])->name('orders.print');
Route::get('orders/{order}', P\Orders\Show::class)->name('orders.show');
Route::get('packing', P\Packing\Scan::class)->name('packing.scan');
Route::get('returns/receive', P\Returns\Receive::class)->name('returns.receive');
Route::get('labels/print', [PrintController::class, 'labels'])->name('labels.print');

Route::get('couriers', P\Couriers\Index::class)->name('couriers.index');
Route::get('customers', P\Customers\Index::class)->name('customers.index');
Route::get('suppliers', P\Suppliers\Index::class)->name('suppliers.index');
Route::get('accounts', P\Accounts\Index::class)->name('accounts.index');
Route::get('reports', P\Reports\Index::class)->name('reports.index');
Route::get('storefront', P\Storefront\Index::class)->name('storefront.index');
Route::get('settings', P\Settings\Index::class)->name('settings.index');
