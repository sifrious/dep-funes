<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Sifrious\Funes\Correction\CorrectionService;
use Sifrious\Funes\FunesServiceProvider;

it('registers the service provider', function (): void {
    expect($this->app->getLoadedProviders())->toHaveKey(FunesServiceProvider::class);
});

it('merges the package configuration', function (): void {
    expect(config('funes'))->toBeArray();
});

it('publishes the package configuration under its own tag', function (): void {
    expect(ServiceProvider::pathsToPublish(FunesServiceProvider::class, 'funes-config'))->not->toBeEmpty();
});

it('registers the correction service', function (): void {
    expect(app(CorrectionService::class))->toBeInstanceOf(CorrectionService::class);
});
