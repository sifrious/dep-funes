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
use Sifrious\Funes\Correction\CorrectionService;
use Sifrious\Funes\Diagram\GrammarParser;
use Sifrious\Funes\Diagram\GrammarTransformer;
use Sifrious\Funes\Diagram\LocalCompactEnglishParser;
use Sifrious\Funes\Diagram\ReedKelloggTransformer;
use Sifrious\Funes\Diagram\SentenceDiagramService;
use Sifrious\Funes\Diagram\SimpleSvgRenderer;
use Sifrious\Funes\Diagram\SvgRenderer;
use Sifrious\Funes\Identity\IdentityRegistry;
use Sifrious\Funes\Identity\SqlIdentityRegistry;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Persistence\SqlObservationStore;
use Sifrious\Funes\Text\SqlTextProjection;
use Sifrious\Funes\Text\TextProjection;

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
        $this->app->singleton(CorrectionService::class, fn ($app): CorrectionService => new CorrectionService(
            $app->make(AcceptanceGateway::class),
            $app->make(ObservationStore::class),
        ));

        $this->app->singleton(AcceptanceBacklog::class, fn ($app): AcceptanceBacklog => new SqlAcceptanceBacklog(
            $this->connection($app),
            $app->make(ObservationStore::class),
        ));

        $this->app->singleton(IdentityRegistry::class, fn ($app): IdentityRegistry => new SqlIdentityRegistry(
            $this->connection($app),
        ));

        $this->app->singleton(TextProjection::class, fn ($app): TextProjection => new SqlTextProjection(
            $this->connection($app),
        ));

        $this->app->singleton(GrammarParser::class, fn (): GrammarParser => new LocalCompactEnglishParser);
        $this->app->singleton(GrammarTransformer::class, fn (): GrammarTransformer => new ReedKelloggTransformer);
        $this->app->singleton(SvgRenderer::class, fn (): SvgRenderer => new SimpleSvgRenderer);
        $this->app->singleton(SentenceDiagramService::class, fn ($app): SentenceDiagramService => new SentenceDiagramService(
            $app->make(GrammarParser::class),
            $app->make(GrammarTransformer::class),
            $app->make(SvgRenderer::class),
            $app->make(ObservationStore::class),
            $this->connection($app),
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
