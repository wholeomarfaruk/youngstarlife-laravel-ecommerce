<?php
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\StickerController;
use App\Http\Controllers\Api\SteadFastController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CartControllerTest;
use App\Http\Controllers\SessionRecordController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\AuthAdmin;
use App\Http\Controllers\CartController;
use Spatie\LaravelPackageTools\Package;

     Route::get('/optimize', function () {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
            exec('php artisan optimize');
            shell_exec('php artisan optimize');
            return '<h3>✅ Application optimized successfully!</h3>';
        });
Route::post('/cart/add/json', [CartController::class, 'add_json_to_cart'])->name('cart.add.json')->withoutMiddleware('auth');

Auth::routes();

// Route::get('/', function () {
//     // return redirect()->away('https://seldomfashion.com/');
//     return view('home');

// });
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
//     Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
// });
Route::post('/record-session', [SessionRecordController::class, 'store']);


Route::get('/shop', [HomeController::class, 'shop'])->name('shop');
Route::get('/category/{slug}', [HomeController::class, 'categoryShow'])->name('category.show');
Route::get('/product/{slug}', [HomeController::class, 'productShow'])->name('product.show');

Route::get('/product/flower-silk-3-piece-dress', [HomeController::class, 'ProductOne'])->name('product.one');
Route::get('/product/safina-3-piece-dress', [HomeController::class, 'ProductTwo'])->name('product.two');
Route::get('/product/aafreen-3-piece-dress', [HomeController::class, 'ProductThree'])->name('product.three');
Route::get('/product/test', [HomeController::class, 'ProductTest'])->name('product.test');


//cart
Route::get('/cart/distroy', [CartController::class, 'cart_distroy'])->name('cart.distroy');
Route::get('/cart/test', [CartController::class, 'test'])->name('cart.test');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{id}', [CartController::class, 'add_to_cart'])->name('cart.add');

Route::delete('/cart/remove/{id}', [CartController::class, 'remove_item'])->name('cart.remove');
Route::put('/cart/increase/{rowId}', [CartController::class, 'increase_quantity'])->name('cart.increase');
Route::put('/cart/decrease/{rowId}', [CartController::class, 'decrease_quantity'])->name('cart.decrease');
Route::delete('/cart/clear', [CartController::class, 'clear_cart'])->name('cart.clear');
Route::get('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
Route::post('/cart/checkout/apply-coupon', [CartController::class, 'apply_coupon'])->name('cart.checkout.apply.coupon');
Route::get('/cart/checkout/remove-coupon', [CartController::class, 'remove_coupon'])->name('cart.checkout.remove.coupon');
Route::post('/cart/checkout/place-order', [CartController::class, 'place_order'])->name('cart.order.place');
Route::get('/order-received', [CartController::class, 'order_received'])->name('order.received')->withoutMiddleware('auth');

Route::post('/cart/checkout/test-place-order', [CartControllerTest::class, 'place_order_test'])->name('cart.order.place.test');

Route::get('/order-received-test', [CartControllerTest::class, 'order_received_test'])->name('order.received.test')->withoutMiddleware('auth');
Route::post('/cart/autosave', [CartController::class, 'orderAutosave'])->name('cart.order.autosave');

// Admin
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', AuthAdmin::class])->group(function () {
          Route::get('/migrate', function () {
            Artisan::call('migrate');
            exec('php artisan migrate');
            shell_exec('php artisan optimize');
            return '<h3>✅ Application migrated successfully!</h3>';
        });
        Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.index');
        // Brands
        Route::get('/brands', [AdminController::class, 'brands'])->name('admin.brands');
        Route::get('/brands/add', [AdminController::class, 'brandsAdd'])->name('admin.brands.add');
        Route::post('/brands/store', [AdminController::class, 'brandStore'])->name('admin.brands.store');
        Route::get('/brands/edit/{id}', [AdminController::class, 'brandEdit'])->name('admin.brands.edit');
        Route::post('/brands/update', [AdminController::class, 'brandUpdate'])->name('admin.brands.update');
        Route::delete('/brands/{id}/delete', [AdminController::class, 'brandDelete'])->name('admin.brands.delete');
        // Categories
        Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories');
        Route::get('/categories/add', [CategoryController::class, 'add'])->name('admin.categories.add');
        Route::post('/categories/store', [CategoryController::class, 'store'])->name('admin.categories.store');
        Route::get('/categories/edit/{id}', [CategoryController::class, 'edit'])->name('admin.categories.edit');
        Route::post('/categories/update', [CategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{id}/delete', [CategoryController::class, 'delete'])->name('admin.categories.delete');
        Route::get('/categories/{id}/manage-products', [CategoryController::class, 'manageProducts'])->name('admin.categories.manage.products');
        Route::post('/categories/{id}/assign-products', [CategoryController::class, 'assignProducts'])->name('admin.categories.assign.products');
        Route::delete('/categories/{id}/unassign-products', [CategoryController::class, 'unassignProducts'])->name('admin.categories.unassign.products');
        // Products
        Route::get('/products', [AdminController::class, 'products'])->name('admin.products');
        Route::get('/products/add', [AdminController::class, 'productsAdd'])->name('admin.products.add');
        Route::post('/products/store', [AdminController::class, 'productStore'])->name('admin.products.store');
        Route::get('/products/edit/{id}', [AdminController::class, 'productEdit'])->name('admin.products.edit');
        Route::put('/products/update', [AdminController::class, 'productUpdate'])->name('admin.products.update');
        Route::delete('/products/{id}/delete', [AdminController::class, 'productDelete'])->name('admin.products.delete');

        // Coupons
        Route::get('/coupons', [AdminController::class, 'coupons'])->name('admin.coupons');
        Route::get('/coupons/add', [AdminController::class, 'couponAdd'])->name('admin.coupons.add');
        Route::post('/coupons/store', [AdminController::class, 'couponStore'])->name('admin.coupons.store');
        Route::get('/coupons/edit/{id}', [AdminController::class, 'couponEdit'])->name('admin.coupons.edit');
        Route::put('/coupons/update/{id}', [AdminController::class, 'couponUpdate'])->name('admin.coupons.update');
        Route::delete('/coupons/{id}/delete', [AdminController::class, 'couponDelete'])->name('admin.coupons.delete');

        // Orders
        Route::get('/orders', [AdminController::class, 'orders'])->name('admin.orders');
        Route::get('/orders/pending', [AdminController::class, 'ordersPending'])->name('admin.orders.pending');
        Route::get('/orders/confirmed', [AdminController::class, 'ordersConfirmed'])->name('admin.orders.confirmed');
        Route::get('/orders/processing', [AdminController::class, 'ordersProcessing'])->name('admin.orders.processing');
        Route::get('/orders/ready', [AdminController::class, 'ordersInReview'])->name('admin.orders.ready');
        Route::get('/orders/in-review', [AdminController::class, 'ordersInReview'])->name('admin.orders.in_review');
        Route::get('/orders/in-transit', [AdminController::class, 'ordersInTransit'])->name('admin.orders.in_transit');
        Route::get('/orders/delivered', [AdminController::class, 'ordersDelivered'])->name('admin.orders.delivered');
        Route::get('/orders/delivery-in-review', [AdminController::class, 'ordersDeliveryInReview'])->name('admin.orders.delivery_in_review');
        Route::get('/orders/on-hold', [AdminController::class, 'ordersOnHold'])->name('admin.orders.on_hold');
        Route::get('/orders/cancelled', [AdminController::class, 'ordersCancelled'])->name('admin.orders.cancelled');
        Route::get('/orders/returned', [AdminController::class, 'ordersReturned'])->name('admin.orders.returned');
        Route::get('/pending-orders-notifications', [OrderController::class, 'fetchPendingOrders'])->name('admin.orders.pending.notifications');
        Route::put('/orders/update-bulk-order-status', [AdminController::class, 'bulkOrderStatusUpdate'])->name('admin.orders.status.update.bulk');

        Route::get('/orders/deleted', [AdminController::class, 'deletedOrders'])->name('admin.orders.deleted');
        Route::get('/orders/{id}/details', [AdminController::class, 'orderDetails'])->name('admin.orders.details');
        Route::put('/orders/{id}/update-status', [AdminController::class, 'orderStatusUpdate'])->name('admin.orders.update');
        Route::delete('/orders/{id}/delete', [AdminController::class, 'deleteOrder'])->name('admin.orders.delete');
        Route::get('/orders/soft-delete/{id}', [AdminController::class, 'ordersoftdelete'])->name('admin.orders.delete.soft');
        Route::get('/orders/export', [AdminController::class, 'exportOrders'])->name('admin.orders.export');
        Route::put('/orders/update/{id}', [AdminController::class, 'updateOrder'])->name('admin.orders.editupdate');
        Route::put('/orders/update/{id}/details', [AdminController::class, 'updateOrderDetails'])->name('admin.orders.update.details');
        Route::get('/orders/add', [AdminController::class, 'orderAdd'])->name('admin.orders.add');
        Route::post('/orders/store', [AdminController::class, 'orderStore'])->name('admin.orders.store');
        //delivery areas
        Route::get('/delivery-areas', [AdminController::class, 'deliveryAreas'])->name('admin.deliveryareas');
        Route::get('/delivery-areas/add', [AdminController::class, 'deliveryAreaAdd'])->name('admin.deliveryareas.add');
        Route::post('/delivery-areas/store', [AdminController::class, 'deliveryAreaStore'])->name('admin.deliveryareas.store');
        Route::get('/delivery-areas/edit/{id}', [AdminController::class, 'deliveryAreaEdit'])->name('admin.deliveryareas.edit');
        Route::put('/delivery-areas/update', [AdminController::class, 'deliveryAreaUpdate'])->name('admin.deliveryareas.update');
        Route::delete('/delivery-areas/{id}/delete', [AdminController::class, 'deliveryAreaDelete'])->name('admin.deliveryareas.delete');

        // Slides
        Route::get('/slides', [AdminController::class, 'slides'])->name('admin.slides');
        Route::get('/slides/add', [AdminController::class, 'slideAdd'])->name('admin.slides.add');
        Route::post('/slides/store', [AdminController::class, 'slideStore'])->name('admin.slides.store');
        Route::get('/slides/{id}/edit', [AdminController::class, 'slideEdit'])->name('admin.slides.edit');
        Route::put('/slides/{id}/update', [AdminController::class, 'slideUpdate'])->name('admin.slides.update');
        Route::delete('/slides/{id}/delete', [AdminController::class, 'slideDelete'])->name('admin.slides.delete');

        //Analytics
        Route::get('/analytics/report', [AdminController::class, 'analytics'])->name('admin.analytics.report');
        Route::get('/google-analytics', [AdminController::class, 'gAnalaytics'])->name('admin.google.analytics');
        Route::put('/google-analytics/update', [AdminController::class, 'gAnalyticsUpdate'])->name('admin.google.analytics.update');
        Route::get('/facebook-pixels', [AdminController::class, 'fbPixels'])->name('admin.facebook.pixels');
        Route::put('/facebook-pixels/update', [AdminController::class, 'fbPixelsUpdate'])->name('admin.facebook.pixels.update');
        Route::get('/session-replays', [SessionRecordController::class, 'index'])->name('admin.session.replays');
        Route::get('/session-replays/{id}', [SessionRecordController::class, 'show'])->name('admin.session.replays.show');

 //User Setting / Admin Setting Start =================================================================
    Route::get("/user-settings",[UserController::class,"index"])->name("admin.user.index");
    Route::put("/user-settings/update",[UserController::class,"update"])->name("admin.user.update");
    //User Setting / Admin Setting End =================================================================

    //steadfast courier api
    Route::get('/steadfast/place-order/{id}', [SteadFastController::class, 'place_order'])->name('admin.steadfast.place_order');


    //notifications
    Route::get('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('admin.notifications.clear.all');
    Route::get('/notifications/mark-as-read/{id}', [NotificationController::class, 'read'])->name('admin.notifications.read');

            //sticker
        Route::post('/generate-sticker', [StickerController::class, 'generate'])->name('admin.generate.sticker');


        //Blacklist
        Route::get('/orders/{id}/customer/block', [AdminController::class, 'orderBlacklistUpdate'])->name('admin.orders.customer.block');
        Route::get('/orders/{id}/customer/unblock', [AdminController::class, 'unblockOrder'])->name('admin.orders.customer.unblock');
});
});
