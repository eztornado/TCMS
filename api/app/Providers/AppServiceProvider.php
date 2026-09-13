<?php

namespace App\Providers;

use App\Services\Shop\ManualGateway;
use App\Services\Shop\PaymentGateway;
use App\Services\Shop\StripeGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }

    public function boot(): void
    {
        $this->configureRateLimiter();
    }

    protected function configureRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('store', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
