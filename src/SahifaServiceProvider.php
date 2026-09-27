<?php

namespace Sahifa\Laravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class SahifaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sahifa.php', 'sahifa');

        $this->app->singleton(Sahifa::class, fn ($app) => new Sahifa(
            $app->make(HttpFactory::class),
            $app->bound('view') ? $app->make('view') : null,
            $app['config']->get('sahifa', []),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/sahifa.php' => config_path('sahifa.php')], 'sahifa-config');
        }
    }
}
