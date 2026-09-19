<?php

namespace App\Providers;

use App\Models\DynamicEntry;
use App\Services\Shop\ManualGateway;
use App\Services\Shop\PaymentGateway;
use App\Services\Shop\StripeGateway;
use App\Services\Sync\Contracts\SyncGateway;
use App\Services\Sync\HttpSyncGateway;
use App\Services\Sync\LoopbackSyncGateway;
use App\Services\Sync\MediaSyncEndpoint;
use App\Services\Sync\SyncEndpoint;
use App\Services\Sync\SyncObserver;
use App\Support\Runtime;
use App\Support\Sync\SyncRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pasarela de pago seleccionable (intercambiable: PaymentGateway).
        $this->app->bind(PaymentGateway::class, function ($app) {
            $configured = config('tcms.payment_gateway', 'auto');

            return match ($configured) {
                'stripe' => new StripeGateway,
                'manual' => new ManualGateway,
                default => config('services.stripe.secret')
                    ? new StripeGateway
                    : new ManualGateway,
            };
        });

        // Transporte de sync: HTTP real en el binario nativo; en web (y en la
        // suite de tests) el endpoint central se invoca en memoria.
        $this->app->bind(SyncGateway::class, function ($app) {
            if (Runtime::isNative() && ! $app->runningUnitTests()) {
                return new HttpSyncGateway;
            }

            return new LoopbackSyncGateway(
                $app->make(SyncEndpoint::class),
                $app->make(MediaSyncEndpoint::class),
            );
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiter();
        $this->registerSyncObserver();

        if (Runtime::isNative()) {
            // En nativo el webview llega por localhost:<puerto> elegido por el
            // runtime. El placeholder de Sanctum confía en el host de la petición
            // actual: las cookies de sesión del panel funcionan en cualquier puerto.
            config([
                'sanctum.stateful' => array_merge(
                    config('sanctum.stateful', []),
                    [Sanctum::$currentRequestHostPlaceholder],
                ),
            ]);
        }
    }

    protected function configureRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('store', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('sync', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }

    /** Captura de cambios syncables: definición, catálogos y tablas dinámicas. */
    protected function registerSyncObserver(): void
    {
        foreach (SyncRegistry::tables() as $definition) {
            $definition['model']::observe(SyncObserver::class);
        }

        // Una sola clase para todas las tablas dinámicas (cm_*).
        DynamicEntry::observe(SyncObserver::class);
    }
}
