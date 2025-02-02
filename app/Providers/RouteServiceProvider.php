<?php

namespace Pterodactyl\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware(['web', 'csrf'])->group(function () {
                Route::middleware('auth')->group(base_path('routes/base.php'));
                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
                Route::middleware(['auth', 'admin'])->prefix('/admin')->group(base_path('routes/admin.php'));
            });

            Route::middleware('api')->group(function () {
                Route::middleware(['application-api', 'throttle:api.application'])
                    ->prefix('/api/application')
                    ->group(base_path('routes/api-application.php'));

                Route::middleware(['client-api', 'throttle:api.client'])
                    ->prefix('/api/client')
                    ->group(base_path('routes/api-client.php'));
            });

            Route::middleware('daemon')->prefix('/api/remote')
                ->group(base_path('routes/api-remote.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting()
    {
        // Authentication rate limiting. For login and checkpoint endpoints we'll apply
        // a limit of 10 requests per minute, for the forgot password endpoint apply a
        // limit of two per minute for the requester so that there is less ability to
        // trigger email spam.
        RateLimiter::for('authentication', function (Request $request) {
            if ($request->route()->named('auth.post.forgot-password')) {
                return Limit::perMinute(2)->by($request->ip());
            }

            return Limit::perMinute(10);
        });

        // Configure the throttles for both the application and client APIs below.
        // This is configurable per-instance in "config/http.php". By default this
        // limiter will be tied to the specific request user, and falls back to the
        // request IP if there is no request user present for the key.
        //
        // This means that an authenticated API user cannot use IP switching to get
        // around the limits.
        RateLimiter::for('api.client', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.client_period'),
                config('http.rate_limit.client')
            )->by($key);
        });

        RateLimiter::for('api.application', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.application_period'),
                config('http.rate_limit.application')
            )->by($key);
        });
    }
}
