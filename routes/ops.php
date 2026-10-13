<?php
// New Ops admin panel. Registered in bootstrap/app.php under /ops with names ops.* (web + auth + OpsAccess).

use App\Http\Controllers\Ops\PrintController;
use App\Livewire\Ops as P;
use Illuminate\Support\Facades\Route;

Route::get('/', P\Dashboard::class)->name('dashboard');
Route::get('trace', P\Trace::class)->name('trace');

// Product & Inventory
Route::get('products', P\Products\Index::class)->name('products.index');
Route::get('products/{product}', P\Products\Edit::class)->name('products.edit');
Route::get('attributes', P\Attributes\Index::class)->name('attributes.index');
Route::get('batches', P\Batches\Index::class)->name('batches.index');
Route::get('purchases', P\Purchases\Index::class)->name('purchases.index');
Route::get('purchases/{po}', P\Purchases\Edit::class)->name('purchases.edit');
Route::get('purchases/{po}/print', [PrintController::class, 'purchaseOrder'])->name('purchases.print');
Route::get('supplier-returns', P\SupplierReturns\Index::class)->name('supplier-returns.index');

// Orders & Packing
Route::get('orders', P\Orders\Index::class)->name('orders.index');
Route::get('orders/by-date', P\Orders\ByDate::class)->name('orders.by-date');
Route::get('orders/campaigns', P\Orders\Campaigns::class)->name('orders.campaigns');
Route::get('orders/drafts', P\Orders\Drafts::class)->name('orders.drafts');
Route::get('orders/bulk', P\Orders\Bulk::class)->name('orders.bulk');   // bulk order sheet + AI Order modal
Route::redirect('orders/ai-entry', 'bulk?intake=1')->name('orders.ai-entry');
Route::get('orders/products', P\Orders\OrderedProducts::class)->name('orders.products');
Route::get('orders/in-courier', P\Orders\InCourier::class)->name('orders.in-courier');
Route::get('orders/print', [PrintController::class, 'invoices'])->name('orders.print');
Route::get('orders/parcel-labels', [PrintController::class, 'parcelLabels'])->name('orders.parcel-labels');
Route::get('orders/{order}', P\Orders\Show::class)->name('orders.show');
Route::get('packing', P\Packing\Scan::class)->name('packing.scan');
Route::get('returns/receive', P\Returns\Receive::class)->name('returns.receive');
Route::get('returns/session/{session}/receipt', [PrintController::class, 'returnReceipt'])->name('returns.receipt');
Route::get('exchanges', P\Exchanges\Index::class)->name('exchanges.index');
Route::get('labels/print', [PrintController::class, 'labels'])->name('labels.print');

Route::get('couriers', P\Couriers\Index::class)->name('couriers.index');
Route::get('couriers/sessions', P\Couriers\Sessions::class)->name('couriers.sessions');
Route::get('couriers/sessions/{session}', P\Couriers\SessionShow::class)->name('couriers.session');
Route::get('couriers/sessions/{session}/print', [PrintController::class, 'entrySession'])->name('couriers.session.print');
Route::get('users', P\Users\Index::class)->name('users.index');
Route::get('users/{user}', P\Users\Show::class)->name('users.show');
Route::get('customers', P\Customers\Index::class)->name('customers.index');
Route::get('blacklist', P\Blacklist\Index::class)->name('blacklist.index');
Route::get('suppliers', P\Suppliers\Index::class)->name('suppliers.index');
Route::get('accounts', P\Accounts\Index::class)->name('accounts.index');
Route::get('reports', P\Reports\Index::class)->name('reports.index');
Route::get('storefront', P\Storefront\Index::class)->name('storefront.index');
Route::get('settings', P\Settings\Index::class)->name('settings.index');
Route::get('profile', P\Profile::class)->name('profile');
