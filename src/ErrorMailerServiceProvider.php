<?php

namespace Artryazanov\ErrorMailer;

use Illuminate\Support\ServiceProvider;

class ErrorMailerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/error-mailer.php' => config_path('error-mailer.php'),
        ], 'error-mailer-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'error-mailer');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/error-mailer'),
        ], 'error-mailer-views');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/error-mailer.php', 'error-mailer'
        );

        $this->app->singleton('error-mailer', function ($app) {
            return new ErrorMailer();
        });
    }
}
