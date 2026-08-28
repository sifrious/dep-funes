<?php

declare(strict_types=1);

namespace Sifrious\Funes;

use Illuminate\Support\ServiceProvider;
use Sifrious\Funes\Acceptance\AcceptanceGateway;
use Sifrious\Funes\Acceptance\SqlAcceptanceGateway;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Persistence\SqlObservationStore;

class FunesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/funes.php', 'funes');

        $this->app->singleton(ObservationStore::class, function ($app): ObservationStore {
            $connection = $app['db']->connection(config('funes.connection'));

            return new SqlObservationStore($connection);
        });
            $this->app->singleton(AcceptanceGateway::class, fn ($app): AcceptanceGateway => new SqlAcceptanceGateway(
            $app->make(\Illuminate\Database\DatabaseManager::class)->connection(),
            $app->make(ObservationStore::class),
        ));

}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/funes.php' => $this->app->configPath('funes.php'),
            ], 'funes-config');
        }
    }
}
