<?php

declare(strict_types=1);

use Sifrious\HarnessContractFixtures\Fixture;

it('consumes the shared request lifecycle history fixture', function (): void {
    $fixture = Fixture::load('request-lifecycle-v1');

    expect($fixture['funes_replay']['delivery_count'])->toBeGreaterThan(1)
        ->and($fixture['funes_replay']['historical_effect_count'])->toBe(1)
        ->and($fixture['sha_traversal']['recovered_origin'])->toBe($fixture['user_input']['id'])
        ->and($fixture['historical_relations'])->not->toBeEmpty();
});
