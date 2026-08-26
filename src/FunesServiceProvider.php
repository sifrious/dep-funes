<?php

declare(strict_types=1);

namespace Sifrious\Funes;

use Illuminate\Support\ServiceProvider;

class FunesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/funes.php', 'funes');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/funes.php' => $this->app->configPath('funes.php'),
            ], 'funes-config');
        }
    }
}
