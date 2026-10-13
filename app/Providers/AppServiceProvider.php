<?php

namespace App\Providers;

use App\Services\AnthropicOrderExtractionService;
use App\Services\GeminiOrderExtractionService;
use App\Services\OpenAiOrderExtractionService;
use App\Services\OpenRouterOrderExtractionService;
use App\Services\OrderExtractionServiceInterface;
use App\Services\Storefront\{CartService, DeviceManager, MenuService};
use Illuminate\Auth\Events\{Failed, Login, Logout, Registered};
use Illuminate\Support\Facades\{Event, View};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // storefront: one device (and cart) per request
        $this->app->singleton(DeviceManager::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(MenuService::class);
        // site settings: one cache read per request (App\Support\SiteSettings)
        $this->app->singleton(\App\Support\SiteSettings::class);

        $this->app->bind(OrderExtractionServiceInterface::class, function () {
            return match (config('services.ai.provider')) {
                'openai' => new OpenAiOrderExtractionService(),
                'gemini' => new GeminiOrderExtractionService(),
                'openrouter' => new OpenRouterOrderExtractionService(),
                default => new AnthropicOrderExtractionService(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // signing in / registering links this browser's device (and its cart) to the account right away
        $link = function ($event) {
            try {
                if ($device = $this->app->make(DeviceManager::class)->current()) $this->app->make(DeviceManager::class)->linkUser($device, $event->user);
            } catch (\Throwable $e) {
                report($e);
            }
        };
        Event::listen(Login::class, $link);
        Event::listen(Registered::class, $link);

        // Ops → My profile → Security: staff sign-in history + new-device alert
        Event::listen(Login::class, fn (Login $e) => $this->app->make(\App\Services\Ops\LoginHistory::class)->record($e->user, 'login'));
        Event::listen(Failed::class, fn (Failed $e) => $this->app->make(\App\Services\Ops\LoginHistory::class)->record($e->user, 'failed'));
        Event::listen(Logout::class, fn (Logout $e) => $this->app->make(\App\Services\Ops\LoginHistory::class)->record($e->user, 'logout'));

        // storefront menus: a category / product change can change or remove a link → rebuild the menu caches
        foreach ([\App\Models\Ops\Category::class, \App\Models\Ops\Product::class] as $model) {
            $model::saved(fn () => MenuService::forget());
            $model::deleted(fn () => MenuService::forget());
        }

        // Ops panel role check also runs on Livewire's own requests (/livewire/update), not only on the page load
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\OpsAccess::class]);

        // cart badge + dynamic menus in the storefront layout
        View::composer('layouts.app', function ($view) {
            $view->with('menus', $this->app->make(MenuService::class));
            try {
                $view->with('cartCount', $this->app->make(CartService::class)->count());
            } catch (\Throwable $e) {
                $view->with('cartCount', 0);
            }
        });

        // Some local Windows PHP installs point openssl.cnf at a path that doesn't
        // exist, which breaks the EC key signing web-push needs for VAPID auth.
        // Only kicks in when that's actually the case; no-op everywhere else
        // (e.g. production Linux, where the default openssl.cnf is valid).
        if (! getenv('OPENSSL_CONF') && ! file_exists('C:\\Program Files\\Common Files\\SSL\\openssl.cnf')) {
            $fallback = 'C:/php-8.3.22/extras/ssl/openssl.cnf';

            if (file_exists($fallback)) {
                putenv("OPENSSL_CONF={$fallback}");
            }
        }
    }
}
