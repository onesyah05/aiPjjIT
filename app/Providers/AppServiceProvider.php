<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('chat', function (Request $request): array {
            return [
                Limit::perMinute(10)->by('minute:'.$request->user()->id),
                Limit::perHour(100)->by('hour:'.$request->user()->id),
            ];
        });

        Event::listen(
            SocialiteWasCalled::class,
            DiscordExtendSocialite::class.'@handle',
        );
    }
}
