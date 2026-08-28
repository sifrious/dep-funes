<?php

declare(strict_types=1);

use Sifrious\Funes\Normalization\NormalizationRule;
use Sifrious\Funes\Normalization\ProviderEvent;
use Sifrious\Funes\Normalization\ProviderEventNormalizer;
use Sifrious\Funes\Normalization\UnsupportedProviderEvent;
use Sifrious\Funes\Reference\CrossPackageReference;

function providerEvent(string $provider, string $id, string $type, array $payload): ProviderEvent
{
    return new ProviderEvent(
        $provider,
        $id,
        $type,
        'sifrious/aleph',
        new DateTimeImmutable('2026-08-28T11:00:00+00:00'),
        new DateTimeImmutable('2026-08-28T11:00:01+00:00'),
        new DateTimeImmutable('2026-08-28T11:00:02+00:00'),
        [new CrossPackageReference('sifrious/funes', 'conversation', 'conversation_01')],
        [new CrossPackageReference('sifrious/funes', 'provenance', 'provenance_01')],
        $payload,
        correlationId: 'workflow_01',
    );
}

function providerEventNormalizer(): ProviderEventNormalizer
{
    return new ProviderEventNormalizer([
        new NormalizationRule('github', 'issue_comment.created', 'comment.created', '1'),
        new NormalizationRule('linear', 'comment.create', 'comment.created', '1'),
    ]);
}

it('normalizes multiple provider types to one historical type without losing evidence', function (): void {
    $githubPayload = ['id' => 41, 'body' => 'GitHub evidence', 'repository' => 'sifrious/landing'];
    $linearPayload = ['id' => 'comment_42', 'body' => 'Linear evidence', 'issue' => 'MME-908'];
    $github = providerEventNormalizer()->normalize(providerEvent('github', '41', 'issue_comment.created', $githubPayload));
    $linear = providerEventNormalizer()->normalize(providerEvent('linear', 'comment_42', 'comment.create', $linearPayload));

    expect($github->type)->toBe('comment.created')
        ->and($linear->type)->toBe('comment.created')
        ->and($github->payload)->toBe($githubPayload)
        ->and($linear->payload)->toBe($linearPayload)
        ->and($github->sourceMetadata)->toBe([
            'provider' => 'github',
            'provider_event_id' => '41',
            'provider_event_type' => 'issue_comment.created',
            'normalization_version' => '1',
        ])
        ->and($github->provenance[0]->id)->toBe('provenance_01');
});

it('returns an identical envelope for a true retry and exposes changed content under the stable event id', function (): void {
    $normalizer = providerEventNormalizer();
    $event = providerEvent('github', '41', 'issue_comment.created', ['body' => 'original']);
    $accepted = $normalizer->normalize($event);
    $retried = $normalizer->normalize($event);
    $changed = $normalizer->normalize(providerEvent('github', '41', 'issue_comment.created', ['body' => 'changed']));

    expect($retried->toArray())->toBe($accepted->toArray())
        ->and($retried->fingerprint())->toBe($accepted->fingerprint())
        ->and($changed->id)->toBe($accepted->id)
        ->and($changed->fingerprint())->not->toBe($accepted->fingerprint());
});

it('fails explicitly for unsupported provider input', function (): void {
    expect(fn () => providerEventNormalizer()->normalize(
        providerEvent('slack', 'event_01', 'message.changed', ['text' => 'unsupported']),
    ))->toThrow(UnsupportedProviderEvent::class, 'Unsupported provider event slack:message.changed.');
});
