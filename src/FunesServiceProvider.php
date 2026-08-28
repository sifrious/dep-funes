<?php

declare(strict_types=1);

namespace Sifrious\Funes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Sifrious\Funes\Acceptance\AcceptanceBacklog;
use Sifrious\Funes\Acceptance\AcceptanceGateway;
use Sifrious\Funes\Acceptance\SqlAcceptanceBacklog;
use Sifrious\Funes\Acceptance\SqlAcceptanceGateway;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Persistence\SqlObservationStore;

class FunesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/funes.php', 'funes');

        $this->app->singleton(ObservationStore::class, fn ($app): ObservationStore => new SqlObservationStore(
            $this->connection($app),
        ));

        $this->app->singleton(AcceptanceGateway::class, fn ($app): AcceptanceGateway => new SqlAcceptanceGateway(
            $this->connection($app),
            $app->make(ObservationStore::class),
        ));

        $this->app->singleton(AcceptanceBacklog::class, fn ($app): AcceptanceBacklog => new SqlAcceptanceBacklog(
            $this->connection($app),
            $app->make(ObservationStore::class),
        ));
    }

    private function connection(Application $app): ConnectionInterface
    {
        return $app->make(DatabaseManager::class)->connection(config('funes.connection'));
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
